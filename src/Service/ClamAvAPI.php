<?php

declare(strict_types=1);

/**
 * ClamAV validation service.
 */

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\VerityBundle\Helpers\VerityResult;
use Dbp\Relay\VerityBundle\Service\VerityProviderInterface;
use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;
use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClientException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\File\File;

class ClamAvAPI implements VerityProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ClamAvClient $client;
    private int $maxsize;

    public function __construct(
        private readonly ConfigurationService $configurationService)
    {
        $bundleConfig = $this->configurationService->getConfig();
        $parts = parse_url($bundleConfig['url']);
        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException('Invalid ClamAV URL in configuration: '.$bundleConfig['url']);
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 3310;
        $this->maxsize = $bundleConfig['maxsize'];
        $this->client = ClamAvClient::createForHost($host, $port);
    }

    public function validate(File $file, string $fileName,
        int $fileSize,
        string $fileHash,
        string $config,
        string $mimetype): VerityResult
    {
        if ($fileSize > $this->maxsize) {
            throw new \RuntimeException("File size exceeded maxsize: {$fileSize} > {$this->maxsize}");
        }

        $result = new VerityResult();
        $result->profileNameRequested = 'scanning for viruses';
        $result->profileNameUsed = 'clamAV';
        $result->validity = false;

        $handle = null;
        try {
            $handle = fopen($file->getPathName(), 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Could not open file: '.$file->getPathName());
            }

            $scanResult = $this->client->scanStream($handle);
        } catch (ClamAvClientException $e) {
            $result->message = 'Network Error';
            $result->errors[] = $e->getMessage();

            return $result;
        } finally {
            if ($handle !== null) {
                fclose($handle);
            }
        }

        if ($scanResult->isClean()) {
            $result->validity = true;
            $result->message = 'accepted';

            return $result;
        }

        if ($scanResult->isVirusFound()) {
            $result->message = 'rejected';
            $result->errors[] = "Virus detected in $fileName: $scanResult->virusName";

            return $result;
        }

        // Scan error (e.g. size limit exceeded)
        $result->message = 'rejected';
        $result->errors[] = "ClamAV error: $scanResult->errorMessage";

        return $result;
    }
}
