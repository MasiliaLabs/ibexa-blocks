<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaBlocks\Conventions;
use Masilia\IbexaBlocks\Form\ContactSettings;
use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Masilia\IbexaBlocks\Search\SearchSettings;

/**
 * The settings of the current site, read when they are used (a worker serves several sites: nothing is kept between two calls).
 *
 * Each setting is a parameter of the namespace "masilia_ibexa_blocks" under its dotted name ("forms.from_address"): the config
 * resolver of Ibexa gives the value of the siteaccess, else of its group, else of "default"; a setting nobody says keeps the
 * default of the value object. The address a site has in the registry of the sites comes before the configured one.
 */
final readonly class SettingsProvider
{
    private const NAMESPACE = 'masilia_ibexa_blocks';

    public function __construct(private ConfigResolverInterface $config)
    {
    }

    /** The value of a setting, or $default when no scope says it. */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->config->hasParameter($name, self::NAMESPACE) ? $this->config->getParameter($name, self::NAMESPACE) : $default;
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
            if (!$this->config->hasParameter($section . '.' . $name, self::NAMESPACE)) {
                continue;
            }
            $target = &$settings;
            foreach (explode('.', $name) as $part) {
                $target = &$target[$part];
            }
            $target = $this->config->getParameter($section . '.' . $name, self::NAMESPACE);
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

    public function newsletter(): NewsletterSettings
    {
        $d = new NewsletterSettings();

        return new NewsletterSettings(
            fromAddress: $this->address('newsletter.from_address') ?? $this->address('forms.from_address'),
            subject: (string) $this->get('newsletter.subject', $d->subject),
            listName: (string) $this->get('newsletter.list_name', $d->listName),
            confirmationTemplate: (string) $this->get('newsletter.confirmation_template', $d->confirmationTemplate),
            statusTemplate: (string) $this->get('newsletter.status_template', $d->statusTemplate),
        );
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
            if ($this->config->hasParameter($parameter, $namespace)) {
                $value = $this->config->getParameter($parameter, $namespace);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
