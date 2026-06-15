<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient;

/**
 * Low-level ClamAV daemon client.
 *
 * All socket I/O goes through a user-supplied factory, making it possible
 * to inject a fake socket (e.g. stream_socket_pair) in tests.
 *
 * @phpstan-type SocketFactory callable(): resource
 */
class ClamAvClient
{
    private const SOCKET_TIMEOUT_SECONDS = 60;
    private const CHUNK_SIZE = 8192;

    /**
     * @param callable(): resource $socketFactory Returns a connected stream resource
     */
    public function __construct(private readonly mixed $socketFactory)
    {
    }

    /**
     * Create a client that connects to a ClamAV daemon via TCP.
     */
    public static function createForHost(string $host, int $port, int $connectTimeoutSeconds = 5): self
    {
        return new self(function () use ($host, $port, $connectTimeoutSeconds) {
            $socket = fsockopen($host, $port, $errNo, $errMsg, $connectTimeoutSeconds);
            if ($socket === false) {
                throw new ClamAvClientException("Could not connect to ClamAV daemon: $errMsg ($errNo)");
            }

            return $socket;
        });
    }

    /**
     * Send a PING command and throw if the daemon does not respond with PONG.
     */
    public function ping(): void
    {
        $socket = $this->connect();
        try {
            $this->socketWrite($socket, "zPING\0");
            $response = fgets($socket);
            if ($response === false) {
                throw new ClamAvClientException('Failed to read response from ClamAV socket');
            }

            $response = trim($response, " \t\n\r\0");
            if ($response !== 'PONG') {
                throw new ClamAvClientException("Unexpected response from ClamAV daemon: $response");
            }
        } finally {
            fclose($socket);
        }
    }

    /**
     * Send a VERSION command and return the version string.
     *
     * Example response: "ClamAV 1.3.1/27150/Wed Jan 10 09:28:00 2024"
     */
    public function version(): string
    {
        $socket = $this->connect();
        try {
            $this->socketWrite($socket, "zVERSION\0");
            $response = fgets($socket);
            if ($response === false) {
                throw new ClamAvClientException('Failed to read response from ClamAV socket');
            }

            return trim($response, " \t\n\r\0");
        } finally {
            fclose($socket);
        }
    }

    /**
     * Send a STATS command and return the full multi-line response.
     *
     * The response includes thread pool state, queue info, and memory stats,
     * terminated by an "END" line.
     */
    public function stats(): string
    {
        $socket = $this->connect();
        try {
            $this->socketWrite($socket, "zSTATS\0");
            $lines = [];
            while (($line = fgets($socket)) !== false) {
                $line = trim($line, "\r\n\0");
                if ($line === 'END') {
                    return implode("\n", $lines);
                }
                $lines[] = $line;
            }

            throw new ClamAvClientException('Failed to read response from ClamAV socket');
        } finally {
            fclose($socket);
        }
    }

    /**
     * Stream data from $dataStream through ClamAV's INSTREAM protocol
     * and return the parsed scan result.
     *
     * @param resource $dataStream A readable stream (e.g. fopen result)
     */
    public function scanStream($dataStream): ClamAvScanResult
    {
        $socket = $this->connect();
        try {
            $this->socketWrite($socket, "zINSTREAM\0");

            while (!feof($dataStream)) {
                $chunk = fread($dataStream, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new ClamAvClientException('Failed to read from data stream');
                }
                if ($chunk === '') {
                    break;
                }
                $this->socketWrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            // Signal end-of-stream.
            $this->socketWrite($socket, pack('N', 0));

            $response = fgets($socket);
            if ($response === false) {
                throw new ClamAvClientException('Failed to read response from ClamAV socket');
            }

            return ClamAvScanResult::fromResponse(trim($response));
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $socket = ($this->socketFactory)();
        stream_set_timeout($socket, self::SOCKET_TIMEOUT_SECONDS);

        return $socket;
    }

    /**
     * @param resource $socket
     */
    private function socketWrite($socket, string $data): void
    {
        if (fwrite($socket, $data) === false) {
            throw new ClamAvClientException('Failed to write to ClamAV socket');
        }
    }
}
