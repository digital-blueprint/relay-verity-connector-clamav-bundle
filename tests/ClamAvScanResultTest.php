<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Tests;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvScanResult;
use PHPUnit\Framework\TestCase;

class ClamAvScanResultTest extends TestCase
{
    public function testClean(): void
    {
        $result = ClamAvScanResult::fromResponse('stream: OK');
        $this->assertTrue($result->isClean());
        $this->assertNull($result->virusName);
        $this->assertNull($result->errorMessage);
    }

    public function testVirusFound(): void
    {
        $result = ClamAvScanResult::fromResponse('stream: Win.Test.EICAR_HDB-1 FOUND');
        $this->assertTrue($result->isVirusFound());
        $this->assertSame('Win.Test.EICAR_HDB-1', $result->virusName);
    }

    public function testScanError(): void
    {
        $result = ClamAvScanResult::fromResponse('stream: Access denied ERROR');
        $this->assertTrue($result->isError());
        $this->assertSame('Access denied', $result->errorMessage);
    }

    public function testSizeLimitExceeded(): void
    {
        $result = ClamAvScanResult::fromResponse('INSTREAM size limit exceeded. ERROR');
        $this->assertTrue($result->isError());
        $this->assertSame('INSTREAM size limit exceeded.', $result->errorMessage);
    }

    public function testUnknownResponse(): void
    {
        $result = ClamAvScanResult::fromResponse('something unexpected');
        $this->assertTrue($result->isError());
        $this->assertSame('something unexpected', $result->errorMessage);
    }
}
