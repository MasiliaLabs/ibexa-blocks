<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaBlocks\Conventions;
use Masilia\IbexaBlocks\Form\ContactSettings;
use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Masilia\IbexaBlocks\Search\SearchSettings;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The settings of the current site, read when they are used (a worker serves several sites: nothing is kept between two calls).
 *
 * Each setting is a parameter of the namespace "masilia_ibexa_blocks" under its dotted name ("forms.from_address"): the config
 * resolver of Ibexa gives the value of the siteaccess, else of its group, else of "default"; a setting nobody says keeps the
 * default of the value object. The address a site has in the registry of the sites comes before the configured one.
 */
final class SettingsProvider implements ResetInterface
{
    private const NAMESPACE = 'masilia_ibexa_blocks';

    public const FEATURED_TEMPLATE = 'blocks/latest_news.twig';

    /** @var array<string, array{0: bool, 1: mixed}> What the resolver said, for the request being served (see reset()) */
    private array $found = [];

    public function __construct(private readonly ConfigResolverInterface $config)
    {
    }

    /**
     * Forgotten between two requests (Symfony resets the services tagged kernel.reset): a worker serves several sites, and the
     * answer of the resolver is the one of the siteaccess of the request, which does not change while it is served.
     */
    public function reset(): void
    {
        $this->found = [];
    }

    /**
     * @return array{0: bool, 1: mixed} Whether the setting is said for the site, and its value
     */
    private function lookup(string $name, string $namespace = self::NAMESPACE): array
    {
        return $this->found[$namespace . ':' . $name] ??= $this->config->hasParameter($name, $namespace)
            ? [true, $this->config->getParameter($name, $namespace)]
            : [false, null];
    }

    /** The value of a setting, or $default when no scope says it. */
    public function get(string $name, mixed $default = null): mixed
    {
        [$found, $value] = $this->lookup($name);

        return $found ? $value : $default;
    }

    /** The settings (dotted names) of the section "conventions", each one a leaf; the tables are leaves as a whole. */
    public const CONVENTIONS = ['page_type', 'blocks_field', 'items_field', 'block_group', 'content_groups', 'items_field_by_type', 'home_type', 'site_type', 'blocks_folder_suffix', 'folder_type'];

    public const SEARCH = [
        'allowed_content_types', 'default_content_types', 'default_sort', 'sorts', 'variations', 'facets', 'category_field', 'tags_field',
        'auto_item_sort_fields', 'auto_item_default_sort_field', 'page.content_types', 'page.items', 'page.popular_searches',
        'api.default_items', 'api.max_items', 'api.max_term_length', 'api.max_tags', 'api.suggestions_cache_ttl',
    ];

    /** The tables of the sections: a scope that says one replaces the one of the scope below. */
    public const TABLES = ['conventions.items_field_by_type', 'search.sorts', 'search.variations', 'search.facets', 'search.auto_item_sort_fields'];

    public function conventions(): Conventions
    {
        return Conventions::fromSettings($this->section('conventions', self::CONVENTIONS));
    }

    public function search(): SearchSettings
    {
        return SearchSettings::fromSettings($this->section('search', self::SEARCH));
    }

    /**
     * The nested array a value object reads, made of the settings of the current site: a key nobody says is absent.
     *
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    private function section(string $section, array $names): array
    {
        $settings = [];
        foreach ($names as $name) {
            [$found, $value] = $this->lookup($section . '.' . $name);
            if (!$found) {
                continue;
            }
            $target = &$settings;
            foreach (explode('.', $name) as $part) {
                $target = &$target[$part];
            }
            $target = $value;
            unset($target);
        }

        return $settings;
    }

    public function forms(): FormSettings
    {
        $d = new FormSettings();

        return new FormSettings(
            contentTypes: array_values(array_map('strval', (array) $this->get('forms.content_types', $d->contentTypes))),
            recipientsField: (string) $this->get('forms.recipients_field', $d->recipientsField),
            senderField: (string) $this->get('forms.sender_field', $d->senderField),
            titleField: (string) $this->get('forms.title_field', $d->titleField),
            fromAddress: $this->address('forms.from_address'),
            template: (string) $this->get('forms.template', $d->template),
            maxFields: (int) $this->get('forms.max_fields', $d->maxFields),
            maxLength: (int) $this->get('forms.max_length', $d->maxLength),
        );
    }

    public function contact(): ContactSettings
    {
        $d = new ContactSettings();

        return new ContactSettings(
            contentTypes: array_values(array_map('strval', (array) $this->get('forms.contact.content_types', $d->contentTypes))),
            fieldsField: (string) $this->get('forms.contact.fields_field', $d->fieldsField),
            recipientsField: (string) $this->get('forms.contact.recipients_field', $d->recipientsField),
            senderField: (string) $this->get('forms.contact.sender_field', $d->senderField),
            successField: (string) $this->get('forms.contact.success_field', $d->successField),
            errorField: (string) $this->get('forms.contact.error_field', $d->errorField),
            template: (string) $this->get('forms.contact.template', $d->template),
            confirmationTemplate: (string) $this->get('forms.contact.confirmation_template', $d->confirmationTemplate),
            captcha: (bool) $this->get('forms.contact.captcha', $d->captcha),
            fromAddress: $this->address('forms.from_address'),
        );
    }

    /**
     * The name of the site in the registry of the sites comes first, as its address does: a site that has one is named in the
     * texts whatever the configuration says ("<subject> — <name>", "the <name> newsletter"), unless the configured text says
     * `{site_name}`, which is where the name goes. Without a name in the registry the configured texts apply as they are.
     */
    public function newsletter(): NewsletterSettings
    {
        $d = new NewsletterSettings();
        $name = $this->siteName();
        $subject = (string) $this->get('newsletter.subject', null);
        $listName = (string) $this->get('newsletter.list_name', null);
        $text = static fn (string $configured, string $default, string $named): string => match (true) {
            $name === null => $configured !== '' ? $configured : $default,
            str_contains($configured, '{site_name}') => str_replace('{site_name}', $name, $configured),
            default => $named,
        };

        return new NewsletterSettings(
            fromAddress: $this->address('newsletter.from_address') ?? $this->address('forms.from_address'),
            subject: $text($subject, $d->subject, $d->subject . ' — ' . $name),
            listName: $text($listName, $d->listName, 'the ' . $name . ' newsletter'),
            confirmationTemplate: (string) $this->get('newsletter.confirmation_template', $d->confirmationTemplate),
            statusTemplate: (string) $this->get('newsletter.status_template', $d->statusTemplate),
            listId: (string) $this->get('newsletter.list_id', $d->listId),
        );
    }

    /** The name of the site in the registry of the sites, when it has one. */
    public function siteName(): ?string
    {
        [$found, $name] = $this->lookup('site_name', 'masilia_ibexa_sites');
        if ($found && is_string($name) && $name !== '') {
            return $name;
        }

        return null;
    }

    /** @return list<string> The block types of a list ("auto_items", "featured", "all_posts") that the current site turns on */
    public function listTypes(string $list): array
    {
        return array_values(array_map('strval', (array) $this->get('blocks.lists.' . $list . '.types', [])));
    }

    /**
     * @return array{source_field: string, source_value: string, type_field: string, count_field: string, default_count: int}
     */
    public function autoItems(): array
    {
        $p = 'blocks.lists.auto_items.';

        return [
            'source_field' => (string) $this->get($p . 'source_field', 'item_source'),
            'source_value' => (string) $this->get($p . 'source_value', 'auto'),
            'type_field' => (string) $this->get($p . 'type_field', 'item_type'),
            'count_field' => (string) $this->get($p . 'count_field', 'items_count'),
            'default_count' => max(1, (int) $this->get($p . 'default_count', 3)),
        ];
    }

    /**
     * @return array{field: string, value: string, template: string}
     */
    public function featured(): array
    {
        $p = 'blocks.lists.featured.';

        return [
            'field' => (string) $this->get($p . 'field', 'view_type'),
            'value' => (string) $this->get($p . 'value', 'Featured'),
            'template' => (string) $this->get($p . 'template', self::FEATURED_TEMPLATE),
        ];
    }

    /** @return list<string> The templates a setting names, used though no file says so (the one of the Featured list, when the site turns it on) */
    public function configuredTemplates(): array
    {
        return $this->listTypes('featured') === [] ? [] : [(string) $this->featured()['template']];
    }

    /** @return list<string> The view types the bundle shows for the block types a theme names */
    public function viewTypes(): array
    {
        return array_values(array_map('strval', (array) $this->get('blocks.view_types', ['blocks', 'block_item', 'line'])));
    }

    /** @return list<string> Block types the project renders itself */
    public function handledByProject(): array
    {
        return array_values(array_map('strval', (array) $this->get('blocks.handled_by_project', [])));
    }

    public function flattenFields(): bool
    {
        return (bool) $this->get('blocks.flatten_fields', false);
    }

    /**
     * @param list<string> $default
     *
     * @return list<string> Where the themes are
     */
    public function themeDirectories(array $default): array
    {
        return array_values(array_map('strval', (array) $this->get('themes.directories', $default)));
    }

    /**
     * @param list<string> $always
     *
     * @return list<string> The directories of content types: those that are always read, then those of the site
     */
    public function modelDirectories(array $always): array
    {
        return array_values(array_unique([...$always, ...array_map('strval', (array) $this->get('model.directories', []))]));
    }

    /** Whether the newsletter is on for the current site. */
    public function newsletterEnabled(): bool
    {
        return (bool) $this->get('newsletter.enabled', false);
    }

    /** The address of the registry of the sites when the site has one, else the configured one. */
    private function address(string $name): ?string
    {
        foreach ([['site_email', 'masilia_ibexa_sites'], [$name, self::NAMESPACE]] as [$parameter, $namespace]) {
            [$found, $value] = $this->lookup($parameter, $namespace);
            if ($found) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
