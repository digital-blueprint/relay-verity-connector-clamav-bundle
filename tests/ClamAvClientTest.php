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
