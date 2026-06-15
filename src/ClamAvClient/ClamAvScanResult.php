<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient;

/**
 * Parsed result of a ClamAV INSTREAM scan.
 *
 * Possible daemon responses:
 *   "stream: OK"                              -> clean
 *   "stream: <signature> FOUND"               -> virus found
 *   "stream: <message> ERROR"                 -> scan error
 *   "INSTREAM size limit exceeded. ERROR"     -> stream too large
 */
class ClamAvScanResult
{
    public const STATUS_CLEAN = 'clean';
    public const STATUS_FOUND = 'found';
    public const STATUS_ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly string $rawResponse,
        public readonly ?string $virusName = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    /**
     * Parse a raw trimmed INSTREAM response line.
     */
    public static function fromResponse(string $response): self
    {
        // "stream: OK"
        if (str_ends_with($response, ' OK') || $response === 'stream: OK') {
            return new self(self::STATUS_CLEAN, $response);
        }

        // "stream: <signature> FOUND"
        if (str_ends_with($response, ' FOUND')) {
            // Extract the signature name between "stream: " and " FOUND"
            $virusName = $response;
            if (str_starts_with($virusName, 'stream: ')) {
                $virusName = substr($virusName, strlen('stream: '));
            }
            $virusName = substr($virusName, 0, -strlen(' FOUND'));

            return new self(self::STATUS_FOUND, $response, virusName: $virusName);
        }

        // "stream: <message> ERROR" or "INSTREAM size limit exceeded. ERROR"
        if (str_ends_with($response, ' ERROR')) {
            $errorMessage = $response;
            if (str_starts_with($errorMessage, 'stream: ')) {
                $errorMessage = substr($errorMessage, strlen('stream: '));
            }
            $errorMessage = substr($errorMessage, 0, -strlen(' ERROR'));

            return new self(self::STATUS_ERROR, $response, errorMessage: $errorMessage);
        }

        // Unknown response format -- treat as error
        return new self(self::STATUS_ERROR, $response, errorMessage: $response);
    }

    public function isClean(): bool
    {
        return $this->status === self::STATUS_CLEAN;
    }

    public function isVirusFound(): bool
    {
        return $this->status === self::STATUS_FOUND;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
