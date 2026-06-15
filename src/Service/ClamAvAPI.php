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
use Symfony\Component\HttpFoundation\File\File;

class ClamAvAPI implements VerityProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const SOCKET_OPEN_TIMEOUT_SECONDS = 5;
    private const SOCKET_TIMEOUT_SECONDS = 60;
    private const CHUNK_SIZE = 8192;

    private string $serverHost;
    private int $serverPort;
    private int $maxsize;

    public function __construct(
        private readonly ConfigurationService $configurationService)
    {
        $bundleConfig = $this->configurationService->getConfig();
        $parts = parse_url($bundleConfig['url']);
        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException('Invalid ClamAV URL in configuration: '.$bundleConfig['url']);
        }
        $this->serverHost = $parts['host'];
        $this->serverPort = isset($parts['port']) ? (int) $parts['port'] : 3310;
        $this->maxsize = $bundleConfig['maxsize'];
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

        $handle = null;
        $socket = null;
        try {
            $handle = fopen($file->getPathName(), 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Could not open file: '.$file->getPathName());
            }

            $socket = fsockopen($this->serverHost, $this->serverPort, $errNo, $errMsg, self::SOCKET_OPEN_TIMEOUT_SECONDS);
            if ($socket === false) {
                throw new \RuntimeException("Could not connect to ClamAV daemon: $errMsg ($errNo)");
            }
            stream_set_timeout($socket, self::SOCKET_TIMEOUT_SECONDS);

            if (fwrite($socket, "zINSTREAM\0") === false) {
                throw new \RuntimeException('Failed to write to ClamAV socket');
            }

            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new \RuntimeException('Failed to read file: '.$file->getPathName());
                }
                if ($chunk === '') {
                    break;
                }
                if (fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                    throw new \RuntimeException('Failed to write to ClamAV socket');
                }
            }

            if (fwrite($socket, pack('N', 0)) === false) {
                throw new \RuntimeException('Failed to write to ClamAV socket');
            }
            $response = fgets($socket);
            if ($response === false) {
                throw new \RuntimeException('Failed to read response from ClamAV socket');
            }
            $response = trim($response);

            $statusCode = 200;
            $content = $response;
        } catch (\Exception $e) {
            $statusCode = 500;
            $content = 'Internal Server Error: '.$e->getMessage();
        } finally {
            if ($handle !== null) {
                fclose($handle);
            }
            if ($socket !== null) {
                fclose($socket);
            }
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

            return $result;
        }

        $result->validity = true;
        $result->message = 'accepted';

        return $result;
    }
}
