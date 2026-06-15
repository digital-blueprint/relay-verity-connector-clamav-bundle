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
            ->children()
                ->scalarNode('host')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->example('localhost')
                ->end()
                ->integerNode('port')
                    ->defaultValue(3310)
                    ->min(1)
                    ->max(65535)
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
