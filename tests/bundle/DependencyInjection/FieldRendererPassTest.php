<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\DependencyInjection;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\DependencyInjection\Compiler\FieldRendererPass;
use Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[AsFieldRenderer(['x_one', 'x_two'])]
final class ProbeRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        return 'probe';
    }
}

final class FieldRendererPassTest extends TestCase
{
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('masilia_blocks.fields.unknown_type', 'auto');
        $container->setParameter('masilia_blocks.fields.ignored_types', []);
        $container->register(FieldRendererRegistry::class, FieldRendererRegistry::class)->setAutowired(true)->setPublic(true);
        (new MasiliaIbexaBlocksBundle())->build($container);

        return $container;
    }

    public function testTheAttributeAloneRegistersARendererForEachOfItsTypes(): void
    {
        $container = $this->container();
        $container->register(ProbeRenderer::class, ProbeRenderer::class)->setAutoconfigured(true)->setPublic(true);

        $container->compile();
        $registry = $container->get(FieldRendererRegistry::class);

        self::assertTrue($registry->supports('x_one'));
        self::assertTrue($registry->supports('x_two'));
        self::assertFalse($registry->supports('x_three'));
    }

    public function testTwoRenderersForOneTypeIsFoundAtCompileTime(): void
    {
        $container = $this->container();
        $container->register('one', ProbeRenderer::class)->setAutoconfigured(true);
        $container->register('two', ProbeRenderer::class)->setAutoconfigured(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The field type "x_one" has two renderers');

        $container->compile();
    }

    public function testWithoutARegistryThePassDoesNothing(): void
    {
        (new FieldRendererPass())->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }
}
