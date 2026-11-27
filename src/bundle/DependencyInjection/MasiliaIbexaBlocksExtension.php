<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
use Ibexa\Contracts\FieldTypeRichText\RichText\Converter;
use Ibexa\HttpCache\Handler\TagHandler;
use Masilia\Bundle\IbexaBlocks\Block\Provider\AllPostsParameters;
use Masilia\Bundle\IbexaBlocks\Block\Provider\AutoItemsParameters;
use Masilia\Bundle\IbexaBlocks\Block\Provider\FeaturedParameters;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\RichtextRenderer;
use Masilia\Bundle\IbexaBlocks\Form\ContactFormParameters;
use Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle;
use Masilia\Bundle\IbexaBlocks\Search\LatestContents;
use Masilia\Bundle\IbexaBlocks\Search\SearchService;
use Masilia\Bundle\IbexaBlocks\Site\IbexaCacheTagger;
use Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits;
use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Site\NovactiveMenuProvider;
use Masilia\Bundle\IbexaBlocks\Site\ToolboxAdminMetaProvider;
use Masilia\Contracts\EditorialToolbox\Meta\AdminMetaBuilderInterface;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\MenuProvider;
use Novactive\EzMenuManager\Service\MenuBuilder;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MasiliaIbexaBlocksExtension extends Extension implements PrependExtensionInterface
{
    /** The subscribers table and its entity belong to the bundle, next to the project's own: mapped, and migrated, when the newsletter is on. */
    public function prepend(ContainerBuilder $container): void
    {
        $enabled = false;
        foreach ($container->getExtensionConfig($this->getAlias()) as $config) {
            foreach ((array) ($config['system'] ?? []) as $scope) {
                $enabled = $enabled || (bool) ($scope['newsletter']['enabled'] ?? false);
            }
        }
        if (!$enabled || !$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['MasiliaIbexaBlocks' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__ . '/../Entity',
            'prefix' => 'Masilia\\Bundle\\IbexaBlocks\\Entity',
            'alias' => 'MasiliaIbexaBlocks',
        ]]]]);
        if ($container->hasExtension('doctrine_migrations')) {
            $container->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => [
                'Masilia\\Bundle\\IbexaBlocks\\Migrations' => __DIR__ . '/../Migrations',
            ]]);
        }
    }

    public function getAlias(): string
    {
        return 'masilia_ibexa_blocks';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('default_settings.yaml');
        $loader->load('services.yaml');
        // The newsletter is a set of services and routes: they are there as soon as one scope turns it on, and each site answers
        // only if its own scope does (the controller says 404 otherwise).
        $scopes = $config['system'] ?? [];
        if (array_filter($scopes, static fn (array $scope): bool => (bool) ($scope['newsletter']['enabled'] ?? false)) !== []) {
            $loader->load('newsletter.yaml');
        }

        // Which scopes the project gave settings to (for masilia:blocks:settings: a name that is no siteaccess nor group is a typo).
        $container->setParameter('masilia_blocks.scopes', array_keys($scopes));
        $this->registerLists($container, $scopes);
        $container->register(RequestRateLimits::class, RequestRateLimits::class)->setArguments([new Reference(SettingsProvider::class), new Reference('cache.app')]);

        // The settings that change from one site to the next: read with the ConfigResolver, in the "masilia_ibexa_blocks" namespace.
        $processor = new ConfigurationProcessor($container, 'masilia_ibexa_blocks');
        $processor->mapSetting('layout_remote_id', $config);
        $processor->mapSetting('layout_menus', $config);
        $processor->mapSetting('favicons', $config);
        // The sections read setting by setting, so that a site that says one leaves the others to its group or to "default".
        foreach ($scopes as $scope => $settings) {
            foreach (['conventions', 'search', 'fields', 'forms', 'newsletter', 'blocks', 'themes', 'model', 'rate_limit'] as $section) {
                foreach (self::leaves((array) ($settings[$section] ?? []), $section) as $name => $value) {
                    $processor->getContextualizer()->setContextualParameter($name, $scope, $value);
                }
            }
        }

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

    /**
     * The providers of the blocks that list contents are always there, for the block types any scope names: each one answers
     * only for the types (and with the field names) of the site the request is for.
     *
     * @param array<string, array<string, mixed>> $scopes
     */
    private function registerLists(ContainerBuilder $container, array $scopes): void
    {
        $typesOf = static fn (string $path): array => array_values(array_unique(array_merge([], ...array_map(
            static fn (array $scope): array => array_values((array) (self::at($scope, $path) ?? [])),
            array_values($scopes),
        ))));
        $provide = static function (string $class, array $arguments, array $types, int $priority) use ($container): void {
            if ($types === []) {
                return;
            }
            $container->setDefinition($class, (new Definition($class, $arguments))->addTag(MasiliaIbexaBlocksBundle::TAG_BLOCK_PARAMETERS, [
                'types' => implode(',', $types),
                'priority' => $priority,
            ]));
        };

        $settings = new Reference(SettingsProvider::class);
        $provide(AutoItemsParameters::class, [new Reference(LatestContents::class), $settings], $typesOf('blocks.lists.auto_items.types'), 10);
        $provide(FeaturedParameters::class, [$settings], $typesOf('blocks.lists.featured.types'), 0);
        $provide(AllPostsParameters::class, [new Reference(SearchService::class), new Reference(UrlGeneratorInterface::class), $settings], $typesOf('blocks.lists.all_posts.types'), 0);

        // Contact forms: the provider builds the form from the block's matrix and mails the answers.
        $contact = $typesOf('forms.contact.content_types');
        if ($contact !== []) {
            $container->setDefinition(ContactFormParameters::class, (new Definition(ContactFormParameters::class))
                ->setAutowired(true)
                ->addTag(MasiliaIbexaBlocksBundle::TAG_BLOCK_PARAMETERS, ['types' => implode(',', $contact), 'priority' => 0]));
        }
    }

    /** @param array<string, mixed> $scope */
    private static function at(array $scope, string $path): mixed
    {
        foreach (explode('.', $path) as $part) {
            if (!is_array($scope) || !array_key_exists($part, $scope)) {
                return null;
            }
            $scope = $scope[$part];
        }

        return $scope;
    }

    /**
     * The settings of a section under their dotted name: a list is one setting, a map is flattened.
     *
     * @param array<array-key, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function leaves(array $node, string $prefix): array
    {
        $leaves = [];
        foreach ($node as $key => $value) {
            if (is_array($value) && !array_is_list($value) && !in_array($prefix . '.' . $key, SettingsProvider::TABLES, true)) {
                $leaves += self::leaves($value, $prefix . '.' . $key);
            } else {
                $leaves[$prefix . '.' . $key] = $value;
            }
        }

        return $leaves;
    }
}
