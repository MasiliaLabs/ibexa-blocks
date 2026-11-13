<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Ibexa\Contracts\FieldTypeRichText\RichText\Converter;
use Ibexa\HttpCache\Handler\TagHandler;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\RichtextRenderer;
use Masilia\Bundle\IbexaBlocks\Site\IbexaCacheTagger;
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
        $container->setParameter('masilia_blocks.fields.unknown_type', $config['fields']['unknown_type']);
        $container->setParameter('masilia_blocks.fields.ignored_types', $config['fields']['ignored_types']);
        $container->setParameter('masilia_blocks.fields.summary_types', $config['fields']['summary_types']);

        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config')))->load('services.yaml');

        // The HTTP cache of Ibexa is optional too: without it there is nothing to tell.
        if (class_exists(TagHandler::class)) {
            $container->setAlias(CacheTagger::class, IbexaCacheTagger::class);
        }

        // Rich text is an optional package of Ibexa: without it there is no renderer for it.
        if (!interface_exists(Converter::class)) {
            $container->removeDefinition(RichtextRenderer::class);
        }
    }
}
