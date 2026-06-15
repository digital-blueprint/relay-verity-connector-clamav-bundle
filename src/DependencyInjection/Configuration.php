<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_verity_connector_clamav');

        $treeBuilder->getRootNode()
            ->validate()
                ->ifTrue(function (array $v): bool {
                    $hasHost = isset($v['host']);
                    $hasSocket = isset($v['socket']);

                    return $hasHost === $hasSocket;
                })
                ->thenInvalid('Either "host" or "socket" must be set, but not both.')
            ->end()
            ->children()
                ->scalarNode('host')
                    ->defaultNull()
                    ->example('localhost')
                ->end()
                ->integerNode('port')
                    ->defaultValue(3310)
                    ->min(1)
                    ->max(65535)
                ->end()
                ->scalarNode('socket')
                    ->defaultNull()
                    ->example('/var/run/clamav/clamd.ctl')
                ->end()
                ->integerNode('max_file_size')
                    ->defaultValue(30 * 1024 * 1024)
                    ->min(1)
                    ->example('10M')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function (string $value): int {
                            return ini_parse_quantity($value);
                        })
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
