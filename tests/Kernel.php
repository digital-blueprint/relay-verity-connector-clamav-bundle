<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\CoreTestKernelTrait;
use Dbp\Relay\VerityConnectorClamavBundle\DbpRelayVerityConnectorClamavBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use CoreTestKernelTrait;

    protected function registerAdditionalBundles(): iterable
    {
        yield new DbpRelayVerityConnectorClamavBundle();
    }

    protected function configureAdditionalContainer(ContainerConfigurator $container): void
    {
        $container->extension('dbp_relay_verity_connector_clamav', [
            'host' => 'localhost',
            'max_file_size' => '1M',
        ]);
    }
}
