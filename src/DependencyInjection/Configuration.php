<?php

namespace Wexample\SymfonyLive\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const DEFAULT_SUBSCRIBER_TOKEN_TTL = 3600;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_live');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('hub_public_url')
            ->defaultValue('%env(MERCURE_PUBLIC_URL)%')
            ->end()
            ->scalarNode('jwt_secret')
            ->defaultValue('%env(MERCURE_JWT_SECRET)%')
            ->end()
            ->integerNode('subscriber_token_ttl')
            ->defaultValue(self::DEFAULT_SUBSCRIBER_TOKEN_TTL)
            ->end()
            ->end();

        return $treeBuilder;
    }
}
