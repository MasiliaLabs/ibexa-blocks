<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class Configuration extends SiteAccessAware\Configuration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('masilia_ibexa_blocks');
        $root = $tree->getRootNode();

        // What changes from one site to the next (under `system`: a siteaccess or a siteaccess group).
        $this->generateScopeBaseNode($root)
            ->scalarNode('layout_remote_id')
                ->info('The remote id of the content that holds the layout of the site (logo, menus text, footer): one per site')
            ->end()
            ->arrayNode('layout_menus')
                ->info('The type of menu of each part of the layout (the menu manager\'s types)')
                ->children()
                    ->scalarNode('header')->end()
                    ->scalarNode('footer')->end()
                    ->scalarNode('footer_bottom')->end()
                ->end()
            ->end();

        $root
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
                ->arrayNode('themes')
                    ->info('Where the themes are')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('directories')
                            ->info('Directories with one folder per theme, each with a theme.yaml: <directory>/<theme>/theme.yaml')
                            ->scalarPrototype()->end()
                            ->defaultValue(['%kernel.project_dir%/templates/themes'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('blocks')
                    ->info('How blocks are shown')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('view_types')
                            ->info('The view types that this bundle shows for any content type that a theme.yaml names. A view the project configures itself (content_view) wins over it.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['blocks', 'block_item', 'line'])
                        ->end()
                        ->booleanNode('flatten_fields')
                            ->info('Also give each field to the template as a variable of its own (hero_banner.twig reads `title`), as well as in `fields`')
                            ->defaultFalse()
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
