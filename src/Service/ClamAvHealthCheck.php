<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

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
            $client = $this->configurationService->createClient();
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
