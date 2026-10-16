<?php

declare(strict_types=1);

namespace Ufo\McpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('ufo_mcp');
        $tree->getRootNode()->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('instructions')->defaultNull()->end()
            ->integerNode('max_body_bytes')->min(1)->defaultValue(4194304)->end()
            ->scalarNode('session_directory')->defaultValue('%kernel.cache_dir%/ufo_mcp_sessions')->end()
            ->integerNode('session_ttl')->min(1)->defaultValue(3600)->end()
            ->append($this->mcpServerInfoNode())
            ->arrayNode('groups')
                ->useAttributeAsKey('name')
                ->requiresAtLeastOneElement()
                ->arrayPrototype()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->scalarNode('instructions')->defaultNull()->end()
                        // scalarNode, not integerNode: an explicit "~" (meaning "inherit")
                        // would fail IntegerNode's type check, which rejects null outright.
                        ->scalarNode('max_body_bytes')->defaultNull()->end()
                        ->scalarNode('session_directory')->defaultNull()->end()
                        ->scalarNode('session_ttl')->defaultNull()->end()
                    ->end()
                    ->append($this->mcpServerInfoNode())
                ->end()
            ->end()
        ->end();

        return $tree;
    }

    /**
     * Fields of an MCP Registry server.json (see the schema at
     * https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json),
     * reused both as the bundle-wide default and, per group, as an override of it —
     * see Ufo\McpBundle\ConfigService\McpServerInfoConfig::overriding().
     */
    private function mcpServerInfoNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('mcp_server_info'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('name')->defaultNull()->end()
                ->scalarNode('title')->defaultNull()->end()
                ->scalarNode('description')->defaultNull()->end()
                ->arrayNode('icons')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('src')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('mime_type')->defaultNull()->end()
                            ->arrayNode('sizes')->scalarPrototype()->end()->end()
                            ->scalarNode('theme')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
