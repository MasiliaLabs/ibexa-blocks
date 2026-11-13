<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection\Compiler;

use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the registry one lazy locator of the renderers, by field type: a renderer is built when its type is first read.
 * Two renderers for one type is a mistake that is found here, at compile time, not in a page.
 */
final class FieldRendererPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FieldRendererRegistry::class)) {
            return;
        }

        $byType = [];
        foreach ($container->findTaggedServiceIds(MasiliaIbexaBlocksBundle::TAG_FIELD_RENDERER) as $id => $tags) {
            foreach ($tags as $attributes) {
                foreach (array_filter(array_map('trim', explode(',', (string) ($attributes['types'] ?? '')))) as $type) {
                    if (isset($byType[$type]) && (string) $byType[$type] !== $id) {
                        throw new \LogicException(sprintf('The field type "%s" has two renderers: "%s" and "%s".', $type, $byType[$type], $id));
                    }
                    $byType[$type] = new Reference($id);
                }
            }
        }

        $container->getDefinition(FieldRendererRegistry::class)
            ->setArgument('$renderers', ServiceLocatorTagPass::register($container, $byType));
    }
}
