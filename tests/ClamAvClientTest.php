<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Tests;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;
use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClientException;
use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvScanResult;
use PHPUnit\Framework\TestCase;

class ClamAvClientTest extends TestCase
{
    /**
     * Create a ClamAvClient whose "socket" is one end of a stream_socket_pair.
     * Returns [ClamAvClient, resource $serverSide].
     *
     * @return array{ClamAvClient, resource}
     */
    private function createMockClient(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertNotFalse($pair, 'stream_socket_pair failed');

        $clientSide = $pair[0];
        $serverSide = $pair[1];

        $client = new ClamAvClient(function () use ($clientSide) {
            return $clientSide;
        });

        return [$client, $serverSide];
    }

    /**
     * @return resource
     */
    private function createDataStream(string $data = 'test file content')
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }

    public function testPing(): void
    {
        [$client, $server] = $this->createMockClient();

        fwrite($server, "PONG\n");
        fflush($server);

        $client->ping();

        $sent = fread($server, 1024);
        fclose($server);

        $this->assertSame("zPING\0", $sent);
    }

    public function testPingUnexpectedResponse(): void
    {
        [$client, $server] = $this->createMockClient();

        fwrite($server, "UNKNOWN\n");
        fflush($server);

        $this->expectException(ClamAvClientException::class);
        $this->expectExceptionMessage('Unexpected response from ClamAV daemon: UNKNOWN');

        $client->ping();
    }

    public function testScanStreamClean(): void
    {
        [$client, $server] = $this->createMockClient();

        $data = 'test file content';
        $dataStream = $this->createDataStream($data);

        fwrite($server, "stream: OK\n");
        fflush($server);

        $result = $client->scanStream($dataStream);

        $sent = stream_get_contents($server);
        fclose($server);
        fclose($dataStream);

        $this->assertTrue($result->isClean());
        $this->assertSame(ClamAvScanResult::STATUS_CLEAN, $result->status);
        $this->assertNull($result->virusName);

        $expected = "zINSTREAM\0"
            .pack('N', strlen($data)).$data
            .pack('N', 0);
        $this->assertSame($expected, $sent);
    }

    public function testScanStreamVirusFound(): void
    {
        [$client, $server] = $this->createMockClient();

        $dataStream = $this->createDataStream();

        fwrite($server, "stream: Win.Test.EICAR_HDB-1 FOUND\n");
        fflush($server);

        $result = $client->scanStream($dataStream);
        fclose($server);
        fclose($dataStream);

        $this->assertTrue($result->isVirusFound());
        $this->assertSame(ClamAvScanResult::STATUS_FOUND, $result->status);
        $this->assertSame('Win.Test.EICAR_HDB-1', $result->virusName);
    }

    public function testScanStreamError(): void
    {
        [$client, $server] = $this->createMockClient();

        $dataStream = $this->createDataStream();

        fwrite($server, "INSTREAM size limit exceeded. ERROR\n");
        fflush($server);

        $result = $client->scanStream($dataStream);
        fclose($server);
        fclose($dataStream);

        $this->assertTrue($result->isError());
        $this->assertSame(ClamAvScanResult::STATUS_ERROR, $result->status);
        $this->assertSame('INSTREAM size limit exceeded.', $result->errorMessage);
    }

    /**
     * Test a full ping roundtrip over a Unix domain socket.
     *
     * We create a temporary Unix socket server, connect via fsockopen
     * (the same way createForSocket does internally), accept the connection
     * on the server side, and write the PONG response before calling ping().
     */
    public function testUnixSocket(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix sockets are not supported on Windows');
        }

        $socketPath = sys_get_temp_dir().'/clamav_test_'.getmypid().'.sock';
        @unlink($socketPath);

        $server = stream_socket_server('unix://'.$socketPath, $errNo, $errMsg);
        $this->assertNotFalse($server, "Failed to create Unix socket: $errMsg ($errNo)");

        try {
            // Connect to the Unix socket (same as createForSocket does internally)
            $clientSocket = fsockopen('unix://'.$socketPath, -1, $errNo, $errMsg, 5);
            $this->assertNotFalse($clientSocket, "fsockopen failed: $errMsg ($errNo)");

            // Accept and pre-write the response before ping() tries to read
            $conn = stream_socket_accept($server, 5);
            $this->assertNotFalse($conn);
            fwrite($conn, "PONG\n");
            fflush($conn);

            $client = new ClamAvClient(function () use ($clientSocket) {
                return $clientSocket;
            });
            $client->ping();

            $sent = fread($conn, 1024);
            $this->assertSame("zPING\0", $sent);

            fclose($conn);
        } finally {
            fclose($server);
            @unlink($socketPath);
        }
    }

    public function testCreateForSocketConnectionFailure(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix sockets are not supported on Windows');
        }

        $client = ClamAvClient::createForSocket('/tmp/nonexistent_clamav_test.sock');

        $this->expectException(ClamAvClientException::class);
        $client->ping();
    }

    public function testConnectionFailure(): void
    {
        $client = new ClamAvClient(function () {
            throw new ClamAvClientException('Connection refused');
        });

        $this->expectException(ClamAvClientException::class);
        $this->expectExceptionMessage('Connection refused');

        $client->ping();
    }
}
