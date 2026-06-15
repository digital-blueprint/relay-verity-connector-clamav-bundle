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
            $bundleConfig = $this->configurationService->getConfig();
            $parts = parse_url($bundleConfig['url']);
            $host = $parts['host'];
            $port = (int) $parts['port'];

            $socket = fsockopen($host, $port, $errNo, $errMsg, 5);
            if ($socket === false) {
                $result->set(CheckResult::STATUS_FAILURE, "Could not connect to ClamAV daemon: $errMsg ($errNo)");

                return $result;
            }

            fwrite($socket, "zPING\0");
            $response = fgets($socket);
            fclose($socket);

            $response = trim($response, " \t\n\r\0");
            if ($response !== 'PONG') {
                $result->set(CheckResult::STATUS_FAILURE, "Unexpected response from ClamAV daemon: $response");

                return $result;
            }

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
