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

        // What a site does not say is left to the value objects (the defaults are theirs), so that its group or "default" can say it.
        self::assertArrayNotHasKey('conventions', $config);
        $conventions = \Masilia\IbexaBlocks\Tests\Support\TestSettings::fromParameters([])->conventions();
        self::assertSame(['page', 'blocks', 'items'], [$conventions->pageType, $conventions->blocksField, $conventions->itemsField]);
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

        $this->process(['system' => ['default' => ['conventions' => ['page_typo' => 'page']]]]);
    }

    public function testASettingAtTheRootIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['conventions' => ['page_type' => 'page']]);
    }

    public function testTheSectionsOfAScopeBecomeSettingsOfItByTheirDottedNames(): void
    {
        $container = $this->container();

        (new MasiliaIbexaBlocksExtension())->load([['system' => [
            'default' => ['conventions' => ['page_type' => 'landing', 'items_field_by_type' => ['cards' => 'cells']], 'search' => ['page' => ['items' => 12]]],
            'site_group' => ['newsletter' => ['subject' => 'Hello']],
        ]]], $container);

        self::assertSame('landing', $container->getParameter('masilia_ibexa_blocks.default.conventions.page_type'));
        self::assertSame(['cards' => 'cells'], $container->getParameter('masilia_ibexa_blocks.default.conventions.items_field_by_type'), 'a table is one setting');
        self::assertSame(12, $container->getParameter('masilia_ibexa_blocks.default.search.page.items'));
        self::assertSame('Hello', $container->getParameter('masilia_ibexa_blocks.site_group.newsletter.subject'));
        self::assertFalse($container->hasParameter('masilia_ibexa_blocks.default.newsletter.subject'));
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

        foreach (['masilia_blocks.themes.directories', 'masilia_blocks.blocks.view_types'] as $parameter) {
            self::assertTrue($container->hasParameter($parameter), $parameter);
        }
        self::assertTrue($container->has(\Masilia\Bundle\IbexaBlocks\Theme\ThemeSettings::class));
    }

    public function testTheLimitsOfThePublicSearchHaveSafeDefaultsAndAreChecked(): void
    {
        $search = \Masilia\IbexaBlocks\Tests\Support\TestSettings::fromParameters([])->search();
        self::assertSame([200, 10, 0], [$search->maxTermLength, $search->maxTags, $search->suggestionsCacheTtl]);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['system' => ['default' => ['search' => ['api' => ['max_term_length' => 0]]]]]);
    }
}
