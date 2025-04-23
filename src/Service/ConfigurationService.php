<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

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
}
