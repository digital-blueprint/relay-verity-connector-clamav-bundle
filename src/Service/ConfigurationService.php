<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;

class ConfigurationService
{
    /** @var array<mixed> */
    private array $config = [];

    /** @param array<mixed> $config */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /** @return array<mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function createClient(): ClamAvClient
    {
        if ($this->config['socket'] !== null) {
            return ClamAvClient::createForSocket($this->config['socket']);
        }

        return ClamAvClient::createForHost($this->config['host'], $this->config['port']);
    }
}
