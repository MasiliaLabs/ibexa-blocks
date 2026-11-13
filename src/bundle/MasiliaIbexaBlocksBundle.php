<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks;

use Masilia\Bundle\IbexaBlocks\DependencyInjection\Compiler\FieldRendererPass;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Front-office rendering of pages made of blocks. A class declares itself a field renderer with #[AsFieldRenderer];
 * nothing is tagged by hand.
 */
final class MasiliaIbexaBlocksBundle extends Bundle
{
    public const TAG_FIELD_RENDERER = 'masilia_blocks.field_renderer';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(
            AsFieldRenderer::class,
            static function (ChildDefinition $definition, AsFieldRenderer $attribute): void {
                $definition->addTag(self::TAG_FIELD_RENDERER, ['types' => implode(',', $attribute->types)]);
            },
        );
        $container->addCompilerPass(new FieldRendererPass());
    }
}
