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
                ->scalarNode('url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->example('localhost:3310')
                ->end()
                ->scalarNode('maxsize')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->example('10485760')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
