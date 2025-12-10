<?php

declare(strict_types=1);

/**
 * ClamAV validation service.
 */

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\VerityBundle\Helpers\VerityResult;
use Dbp\Relay\VerityBundle\Service\VerityProviderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClamAvAPI implements VerityProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationService $configurationService)
    {
    }

    public function validate($fileContent, $fileName, $fileSize, $sha1sum, $config, $mimetype): VerityResult
    {
        $bundleConfig = $this->configurationService->getConfig();
        [$serverUrl, $serverPort] = explode(':', $bundleConfig['url']);
        $maxsize = $bundleConfig['maxsize'];

        if ($fileSize > $maxsize) {
            throw new \Exception("File size exceeded maxsize: {$fileSize} > {$maxsize}");
        }

        $tempFile = null;
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'clamscan_');
            file_put_contents($tempFile, $fileContent);

            $socket = fsockopen($serverUrl, (int) $serverPort, $errNo, $errMsg);
            if (!$socket) {
                throw new \Exception("Could not connect to ClamAV daemon: $errMsg ($errNo)");
            }

            fwrite($socket, "nINSTREAM\n");

            $handle = fopen($tempFile, 'rb');
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                $size = pack('N', strlen($chunk));
                fwrite($socket, $size);
                fwrite($socket, $chunk);
            }
            fclose($handle);

            fwrite($socket, pack('N', 0));

            $response = trim(fgets($socket));
            fclose($socket);
            unlink($tempFile);

            $statusCode = 200;
            $content = $response;
        } catch (TransportExceptionInterface $e) {
            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
            $statusCode = 500;
            $content = 'Internal Server Error: '.$e->getMessage();
        }

        $result = new VerityResult();
        $result->profileNameRequested = 'scanning for viruses';
        $result->profileNameUsed = 'clamAV';
        $result->validity = false;

        if ($statusCode !== 200) {
            $result->message = 'Network Error';
            $result->errors[] = $statusCode.' '.$content;

            return $result;
        }

        if (strpos($content, 'OK') === false) {
            $result->message = 'rejected';
            $result->errors[] = 'Virus detected in '.str_replace('stream', $fileName, $content);
        }

        $result->validity = true;
        $result->message = 'accepted';

        return $result;
    }
}
