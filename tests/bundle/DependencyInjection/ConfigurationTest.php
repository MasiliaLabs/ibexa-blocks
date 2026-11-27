<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\DependencyInjection;

use Masilia\Bundle\IbexaBlocks\DependencyInjection\Configuration;
use Masilia\Bundle\IbexaBlocks\DependencyInjection\MasiliaIbexaBlocksExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('ibexa.site_access.list', ['site', 'admin']);
        $container->setParameter('ibexa.site_access.groups', ['site_group' => ['site'], 'admin_group' => ['admin']]);
        $container->setParameter('ibexa.site_access.groups_by_site_access', ['site' => ['site_group'], 'admin' => ['admin_group']]);
        $container->setParameter('ibexa.site_access.config', []);

        return $container;
    }

    public function testTheDefaultsAreTheMasiliaWay(): void
    {
        $config = $this->process([]);

        self::assertSame('page', $config['conventions']['page_type']);
        self::assertSame('blocks', $config['conventions']['blocks_field']);
        self::assertSame('items', $config['conventions']['items_field']);
        self::assertContains('blocks', $config['blocks']['view_types']);
    }

    public function testASiteSaysItsIconsAsAFolderOrAsLinks(): void
    {
        $links = [['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => 'build/images/a/logo.svg']];
        $config = $this->process(['system' => [
            'site_group' => ['favicons' => 'build/images/a/icons'],
            'other_group' => ['favicons' => $links],
        ]]);

        self::assertSame('build/images/a/icons', $config['system']['site_group']['favicons']);
        self::assertSame($links, $config['system']['other_group']['favicons']);
    }

    public function testAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['conventions' => ['page_typo' => 'page']]);
    }

    public function testTheSettingsOfASiteBecomeParametersOfItsScope(): void
    {
        $container = $this->container();

        (new MasiliaIbexaBlocksExtension())->load([['system' => [
            'site_group' => ['favicons' => 'build/images/a/icons', 'layout_remote_id' => 'site_layout'],
        ]]], $container);

        // The scope is the one the setting was given under (the ConfigResolver finds it from the siteaccess and its groups at run time).
        self::assertSame('build/images/a/icons', $container->getParameter('masilia_ibexa_blocks.site_group.favicons'));
        self::assertSame('site_layout', $container->getParameter('masilia_ibexa_blocks.site_group.layout_remote_id'));
        self::assertFalse($container->hasParameter('masilia_ibexa_blocks.admin_group.favicons'), 'a scope the setting was not given under has none');
    }

    public function testTheExtensionDeclaresTheParametersTheServicesRead(): void
    {
        $container = $this->container();

        (new MasiliaIbexaBlocksExtension())->load([[]], $container);

        foreach (['masilia_blocks.conventions', 'masilia_blocks.search', 'masilia_blocks.themes.directories', 'masilia_blocks.fields.summary_types'] as $parameter) {
            self::assertTrue($container->hasParameter($parameter), $parameter);
        }
        self::assertTrue($container->has(\Masilia\Bundle\IbexaBlocks\Theme\ThemeSettings::class));
    }
}
