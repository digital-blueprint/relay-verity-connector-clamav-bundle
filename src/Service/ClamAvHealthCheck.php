<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;
use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;

class ClamAvHealthCheck implements CheckInterface
{
    public function __construct(private readonly ConfigurationService $configurationService)
    {
    }

    public function getName(): string
    {
        return 'verity-connector-clamav';
    }

    private function checkConnection(): CheckResult
    {
        $result = new CheckResult('Check if ClamAV daemon is reachable');

        try {
            $bundleConfig = $this->configurationService->getConfig();
            $parts = parse_url($bundleConfig['url']);
            $host = $parts['host'];
            $port = (int) $parts['port'];

            $client = ClamAvClient::createForHost($host, $port);
            $client->ping();

            $result->set(CheckResult::STATUS_SUCCESS);
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        return [$this->checkConnection()];
    }
}
