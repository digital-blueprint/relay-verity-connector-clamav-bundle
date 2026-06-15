<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Tests;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;
use Dbp\Relay\VerityConnectorClamavBundle\Service\ClamAvAPI;
use Dbp\Relay\VerityConnectorClamavBundle\Service\ConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class ClamAvAPITest extends TestCase
{
    private function createAPI(ClamAvClient $client, int $maxsize = 1000000): ClamAvAPI
    {
        $config = new ConfigurationService();
        $config->setConfig(['url' => 'localhost:3310', 'maxsize' => $maxsize]);

        $api = new ClamAvAPI($config);
        $api->setClient($client);

        return $api;
    }

    /**
     * @return array{ClamAvClient, resource}
     */
    private function createMockClient(string $response): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertNotFalse($pair);

        fwrite($pair[1], $response);
        fflush($pair[1]);

        $client = new ClamAvClient(function () use ($pair) {
            return $pair[0];
        });

        return [$client, $pair[1]];
    }

    private function createTempFile(string $content = 'test'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clamav_test_');
        file_put_contents($path, $content);

        return $path;
    }

    public function testValidateClean(): void
    {
        [$client, $server] = $this->createMockClient("stream: OK\n");
        $api = $this->createAPI($client);

        $path = $this->createTempFile();
        try {
            $result = $api->validate(new File($path), 'test.txt', filesize($path), '', '', '');
        } finally {
            fclose($server);
            unlink($path);
        }

        $this->assertTrue($result->validity);
        $this->assertSame('accepted', $result->message);
    }

    public function testValidateVirusFound(): void
    {
        [$client, $server] = $this->createMockClient("stream: Win.Test FOUND\n");
        $api = $this->createAPI($client);

        $path = $this->createTempFile();
        try {
            $result = $api->validate(new File($path), 'evil.bin', filesize($path), '', '', '');
        } finally {
            fclose($server);
            unlink($path);
        }

        $this->assertFalse($result->validity);
        $this->assertSame('rejected', $result->message);
        $this->assertStringContainsString('evil.bin', $result->errors[0]);
        $this->assertStringContainsString('Win.Test', $result->errors[0]);
    }

    public function testValidateMaxsizeExceeded(): void
    {
        [$client, $server] = $this->createMockClient("stream: OK\n");
        $api = $this->createAPI($client, maxsize: 2);

        $path = $this->createTempFile('too large');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('maxsize');
            $api->validate(new File($path), 'big.bin', filesize($path), '', '', '');
        } finally {
            fclose($server);
            unlink($path);
        }
    }
}
