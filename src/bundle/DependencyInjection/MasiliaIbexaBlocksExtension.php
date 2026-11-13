<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
use Ibexa\Contracts\FieldTypeRichText\RichText\Converter;
use Ibexa\HttpCache\Handler\TagHandler;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\RichtextRenderer;
use Masilia\Bundle\IbexaBlocks\Site\IbexaCacheTagger;
use Masilia\Bundle\IbexaBlocks\Site\NovactiveMenuProvider;
use Masilia\Bundle\IbexaBlocks\Site\ToolboxAdminMetaProvider;
use Masilia\Contracts\EditorialToolbox\Meta\AdminMetaBuilderInterface;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\MenuProvider;
use Novactive\EzMenuManager\Service\MenuBuilder;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MasiliaIbexaBlocksExtension extends Extension
{
    public function getAlias(): string
    {
        return 'masilia_ibexa_blocks';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('masilia_blocks.conventions', $config['conventions']);
        $container->setParameter('masilia_blocks.blocks.view_types', $config['blocks']['view_types']);
        $container->setParameter('masilia_blocks.search', $config['search']);
        $container->setParameter('masilia_blocks.forms', $config['forms']);
        $container->setParameter('masilia_blocks.themes.directories', $config['themes']['directories']);
        $container->setParameter('masilia_blocks.blocks.flatten_fields', $config['blocks']['flatten_fields']);
        $container->setParameter('masilia_blocks.fields.unknown_type', $config['fields']['unknown_type']);
        $container->setParameter('masilia_blocks.fields.ignored_types', $config['fields']['ignored_types']);
        $container->setParameter('masilia_blocks.fields.summary_types', $config['fields']['summary_types']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('default_settings.yaml');
        $loader->load('services.yaml');

        // The settings that change from one site to the next: read with the ConfigResolver, in the "masilia_ibexa_blocks" namespace.
        $processor = new ConfigurationProcessor($container, 'masilia_ibexa_blocks');
        $processor->mapSetting('layout_remote_id', $config);
        $processor->mapSetting('layout_menus', $config);

        // The HTTP cache of Ibexa is optional too: without it there is nothing to tell.
        if (class_exists(TagHandler::class)) {
            $container->setAlias(CacheTagger::class, IbexaCacheTagger::class);
        }

        // The menu manager and the editing toolbox are optional: with them, their adapters are what the layout and the pages use.
        if (class_exists(MenuBuilder::class)) {
            $container->setAlias(MenuProvider::class, NovactiveMenuProvider::class);
        } else {
            $container->removeDefinition(NovactiveMenuProvider::class);
        }
        if (interface_exists(AdminMetaBuilderInterface::class)) {
            $container->setAlias(AdminMetaProvider::class, ToolboxAdminMetaProvider::class);
        } else {
            $container->removeDefinition(ToolboxAdminMetaProvider::class);
        }

        // Rich text is an optional package of Ibexa: without it there is no renderer for it.
        if (!interface_exists(Converter::class)) {
            $container->removeDefinition(RichtextRenderer::class);
        }
    }
}
