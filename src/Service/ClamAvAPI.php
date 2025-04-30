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

        try {
            // TODO:
            // Create a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'clamscan_');
            file_put_contents($tempFile, $fileContent);

            // Connect to ClamAV daemon
            $socket = fsockopen($serverUrl, (int)$serverPort, $errNo, $errMsg);
            if (!$socket) {
                unlink($tempFile);
                throw new \Exception("Could not connect to ClamAV daemon: $errMsg ($errNo)");
            }

            // Send the INSTREAM command
            fwrite($socket, "nINSTREAM\n");

            // Read file in chunks and send to ClamAV
            $handle = fopen($tempFile, "rb");
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                $size = pack('N', strlen($chunk));
                fwrite($socket, $size);
                fwrite($socket, $chunk);
            }
            fclose($handle);

            // Send zero-length chunk to indicate end of file
            fwrite($socket, pack('N', 0));

            // Get the response
            $response = trim(fgets($socket));
            fclose($socket);

            // Delete the temporary file
            unlink($tempFile);

            $statusCode = 200;
            $content = $response;
        } catch (TransportExceptionInterface $e) {
                $statusCode = 500;
                $content = 'Internal Server Error: ' . $e->getMessage();
        }

        $result = new VerityResult();
        $result->profileNameRequested = 'scanning for viruses';

        // Check if the request was successful
        if ($statusCode !== 200) {
            $result->validity = false;
            $result->message = 'Network Error';
            $result->errors[] = $statusCode.' '.$content;

            return $result;
        }

        $result->validity = true;
        $result->message = 'accepted';
        $result->profileNameUsed = 'clamAV';

        // Check the ClamAV response
        if (strpos($content, 'OK') === false) {
            $result->validity = false;
            $result->message = 'rejected';
            $result->errors[] = 'Virus detected in ' . str_replace('stream', $fileName, $content);
        }

        return $result;
    }
}
