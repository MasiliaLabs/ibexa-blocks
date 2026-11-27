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

        $container->setParameter('masilia_blocks.conventions', $config['conventions']);
        $container->setParameter('masilia_blocks.blocks.view_types', $config['blocks']['view_types']);
        $container->setParameter('masilia_blocks.search', $config['search']);
        $container->setParameter('masilia_blocks.model.directories', array_values(array_unique(['%kernel.project_dir%/config/content_model', ...$config['model']['directories']])));
        $container->setParameter('masilia_blocks.themes.directories', $config['themes']['directories']);
        $container->setParameter('masilia_blocks.blocks.handled_by_project', $config['blocks']['handled_by_project']);
        $container->setParameter('masilia_blocks.blocks.flatten_fields', $config['blocks']['flatten_fields']);
        $container->setParameter('masilia_blocks.fields.unknown_type', $config['fields']['unknown_type']);
        $container->setParameter('masilia_blocks.fields.ignored_types', $config['fields']['ignored_types']);
        $container->setParameter('masilia_blocks.fields.summary_types', $config['fields']['summary_types']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('default_settings.yaml');
        $loader->load('services.yaml');
        // The newsletter is a set of services and routes: they are there as soon as one scope turns it on, and each site answers
        // only if its own scope does (the controller says 404 otherwise).
        $scopes = $config['system'] ?? [];
        if (array_filter($scopes, static fn (array $scope): bool => (bool) ($scope['newsletter']['enabled'] ?? false)) !== []) {
            $loader->load('newsletter.yaml');
        }

        $container->setParameter('masilia_blocks.configured_templates', array_filter([(string) ($config['blocks']['lists']['featured']['template'] ?? '')]));
        $this->registerLists($container, $config);
        $this->registerRateLimits($container, $config['rate_limit']);

        // The settings that change from one site to the next: read with the ConfigResolver, in the "masilia_ibexa_blocks" namespace.
        $processor = new ConfigurationProcessor($container, 'masilia_ibexa_blocks');
        $processor->mapSetting('layout_remote_id', $config);
        $processor->mapSetting('layout_menus', $config);
        $processor->mapSetting('favicons', $config);
        // The sections read setting by setting, so that a site that says one leaves the others to its group or to "default".
        foreach ($scopes as $scope => $settings) {
            foreach (['forms', 'newsletter'] as $section) {
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
     * The blocks that list contents are on for the block types the project names: the providers are made here, with the field
     * names of the settings, instead of being found by an attribute that could not know them.
     *
     * @param array<string, mixed> $config
     */
    private function registerLists(ContainerBuilder $container, array $config): void
    {
        $lists = $config['blocks']['lists'];
        $provide = static function (string $class, array $arguments, array $types, int $priority) use ($container): void {
            if ($types === []) {
                return;
            }
            $container->setDefinition($class, (new Definition($class, $arguments))->addTag(MasiliaIbexaBlocksBundle::TAG_BLOCK_PARAMETERS, [
                'types' => implode(',', $types),
                'priority' => $priority,
            ]));
        };

        $auto = $lists['auto_items'];
        $provide(AutoItemsParameters::class, [new Reference(LatestContents::class), ['items_field' => $config['conventions']['items_field'], 'items_field_by_type' => $config['conventions']['items_field_by_type']] + array_diff_key($auto, ['types' => 1])], $auto['types'], 10);
        $featured = $lists['featured'];
        $provide(FeaturedParameters::class, [array_diff_key($featured, ['types' => 1])], $featured['types'], 0);
        $provide(AllPostsParameters::class, [new Reference(SearchService::class), new Reference(UrlGeneratorInterface::class)], $lists['all_posts']['types'], 0);

        // Contact forms: the provider builds the form from the block's matrix and mails the answers.
        // The types of every scope: the provider answers only for the types of the current site.
        $contact = array_values(array_unique(array_merge([], ...array_map(static fn (array $scope): array => array_values((array) ($scope['forms']['contact']['content_types'] ?? [])), array_values($config['system'] ?? [])))));
        if ($contact !== []) {
            $container->setDefinition(ContactFormParameters::class, (new Definition(ContactFormParameters::class))
                ->setAutowired(true)
                ->addTag(MasiliaIbexaBlocksBundle::TAG_BLOCK_PARAMETERS, ['types' => implode(',', $contact), 'priority' => 0]));
        }
    }

    /**
     * The limits of the public endpoints that send a mail: a limiter for each one the site configures, kept in the application cache.
     *
     * @param array<string, array{limit?: int, interval?: string}> $limits
     */
    private function registerRateLimits(ContainerBuilder $container, array $limits): void
    {
        $factories = [];
        foreach ($limits as $name => $limit) {
            if ($limit === []) {
                continue;
            }
            if (!class_exists(\Symfony\Component\RateLimiter\RateLimiterFactory::class)) {
                throw new \LogicException('masilia_ibexa_blocks.rate_limit needs symfony/rate-limiter: composer require symfony/rate-limiter');
            }
            $factories[$name] = new Definition(\Symfony\Component\RateLimiter\RateLimiterFactory::class, [
                ['id' => 'masilia_blocks_' . $name, 'policy' => 'sliding_window', 'limit' => $limit['limit'], 'interval' => $limit['interval']],
                new Definition(\Symfony\Component\RateLimiter\Storage\CacheStorage::class, [new Reference('cache.app')]),
            ]);
        }

        $container->register(\Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits::class, \Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits::class)->setArguments([$factories]);
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
            if (is_array($value) && !array_is_list($value)) {
                $leaves += self::leaves($value, $prefix . '.' . $key);
            } else {
                $leaves[$prefix . '.' . $key] = $value;
            }
        }

        return $leaves;
    }
}
