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
    private const MAX_RESPONSE_LENGTH = 65536;

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
            $socket = @fsockopen($host, $port, $errNo, $errMsg, $connectTimeoutSeconds);
            if ($socket === false) {
                throw new ClamAvClientException("Could not connect to ClamAV daemon: $errMsg ($errNo)");
            }

            return $socket;
        });
    }

    /**
     * Create a client that connects to a ClamAV daemon via a Unix domain socket.
     */
    public static function createForSocket(string $path, int $connectTimeoutSeconds = 5): self
    {
        return new self(function () use ($path, $connectTimeoutSeconds) {
            $socket = @fsockopen('unix://'.$path, -1, $errNo, $errMsg, $connectTimeoutSeconds);
            if ($socket === false) {
                throw new ClamAvClientException("Could not connect to ClamAV daemon at $path: $errMsg ($errNo)");
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
            $this->writeRecord($socket, 'PING');
            $response = $this->readRecord($socket);
            if ($response !== 'PONG') {
                throw new ClamAvClientException("Unexpected response from ClamAV daemon: $response");
            }
        } finally {
            @fclose($socket);
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
            $this->writeRecord($socket, 'VERSION');

            $response = $this->readRecord($socket);
            if ($response === '') {
                throw new ClamAvClientException('Received an empty response from ClamAV daemon');
            }

            return $response;
        } finally {
            @fclose($socket);
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
            $this->writeRecord($socket, 'STATS');
            $lines = explode("\n", $this->readRecord($socket));
            if (array_pop($lines) !== 'END') {
                throw new ClamAvClientException('Unexpected STATS response from ClamAV daemon');
            }

            return implode("\n", $lines);
        } finally {
            @fclose($socket);
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
            $this->writeRecord($socket, 'INSTREAM');

            while (true) {
                $chunk = @fread($dataStream, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new ClamAvClientException('Failed to read from data stream');
                }
                if ($chunk === '') {
                    if (@feof($dataStream)) {
                        break;
                    }

                    throw new ClamAvClientException('Failed to read from data stream');
                }
                $this->writeChunk($socket, $chunk);
            }

            // Signal end-of-stream.
            $this->writeChunk($socket, '');

            return ClamAvScanResult::fromResponse($this->readRecord($socket));
        } finally {
            @fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $socket = ($this->socketFactory)();
        if (!@stream_set_timeout($socket, self::SOCKET_TIMEOUT_SECONDS)) {
            @fclose($socket);

            throw new ClamAvClientException('Failed to configure ClamAV socket');
        }

        return $socket;
    }

    /**
     * @param resource $socket
     */
    private function writeRecord($socket, string $command): void
    {
        $this->writeAll($socket, 'z'.$command."\0");
    }

    /**
     * @param resource $socket
     */
    private function writeChunk($socket, string $data): void
    {
        $this->writeAll($socket, pack('N', strlen($data)).$data);
    }

    /**
     * @param resource $socket
     */
    private function writeAll($socket, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = @fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) {
                $metadata = stream_get_meta_data($socket);
                if ($metadata['timed_out']) {
                    throw new ClamAvClientException('Timed out writing to ClamAV socket');
                }

                throw new ClamAvClientException('Failed to write to ClamAV socket');
            }

            $offset += $written;
        }
    }

    /**
     * @param resource $socket
     */
    private function readRecord($socket): string
    {
        $record = '';

        while (true) {
            $byte = @fread($socket, 1);
            if ($byte === false || $byte === '') {
                $metadata = stream_get_meta_data($socket);
                if ($metadata['timed_out']) {
                    throw new ClamAvClientException('Timed out reading response from ClamAV socket');
                }
                if ($metadata['eof']) {
                    throw new ClamAvClientException('ClamAV socket closed before the response was complete');
                }

                throw new ClamAvClientException('Failed to read response from ClamAV socket');
            }

            if ($byte === "\0") {
                return $record;
            }

            $record .= $byte;
            if (strlen($record) > self::MAX_RESPONSE_LENGTH) {
                throw new ClamAvClientException('Response from ClamAV daemon exceeded the maximum length');
            }
        }
    }
}
