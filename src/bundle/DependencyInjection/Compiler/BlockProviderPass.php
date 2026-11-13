<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection\Compiler;

use Masilia\Bundle\IbexaBlocks\Block\BlockProviders;
use Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/** Gives BlockProviders its providers (lazily) and the map of which content type each one serves. */
final class BlockProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BlockProviders::class)) {
            return;
        }

        $refs = [];
        $byType = [];
        foreach ($container->findTaggedServiceIds(MasiliaIbexaBlocksBundle::TAG_BLOCK_PARAMETERS) as $id => $tags) {
            $refs[$id] = new Reference($id);
            foreach ($tags as $attributes) {
                $viewTypes = array_values(array_filter(array_map('trim', explode(',', (string) ($attributes['viewTypes'] ?? '')))));
                foreach (array_filter(array_map('trim', explode(',', (string) ($attributes['types'] ?? '')))) as $type) {
                    $byType[$type][] = ['id' => $id, 'viewTypes' => $viewTypes, 'priority' => (int) ($attributes['priority'] ?? 0)];
                }
            }
        }

        $container->getDefinition(BlockProviders::class)
            ->setArgument('$providers', ServiceLocatorTagPass::register($container, $refs))
            ->setArgument('$byType', $byType);
    }
}
