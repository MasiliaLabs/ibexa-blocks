<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('masilia_ibexa_blocks');
        $tree->getRootNode()
            ->children()
                ->arrayNode('conventions')
                    ->info('How the sites of this project are built. The defaults are the Masilia way.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('page_type')->defaultValue('page')->info('The content type of a page')->end()
                        ->scalarNode('blocks_field')->defaultValue('blocks')->info('The field of a page that holds its blocks')->end()
                        ->scalarNode('items_field')->defaultValue('items')->info('The field of a block that holds its child items')->end()
                        ->scalarNode('block_group')->defaultValue('Blocks')->info('The group of content types that are blocks')->end()
                        ->arrayNode('content_groups')
                            ->info('The groups of the other content types the site is made of')
                            ->scalarPrototype()->end()
                            ->defaultValue(['Content', 'Block items', 'Media', 'Taxonomies', 'Configurations'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('fields')
                    ->info('How the values of fields reach the templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('unknown_type')
                            ->values(['auto', 'error', 'null'])
                            ->defaultValue('auto')
                            ->info('A field type no renderer handles: "error" refuses it, "null" logs it and gives null, "auto" is "error" in debug and "null" otherwise')
                        ->end()
                        ->arrayNode('summary_types')
                            ->info('Content types that a relation field gives as {id, name} instead of a whole content (tags, categories)')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('ignored_types')
                            ->info('Field types that are never rendered (they give null, without a log)')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
