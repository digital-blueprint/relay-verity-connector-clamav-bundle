<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;

class ConfigurationService
{
    private array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function createClient(): ClamAvClient
    {
        return ClamAvClient::createForHost($this->config['host'], $this->config['port']);
    }
}
