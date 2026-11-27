<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class Configuration extends SiteAccessAware\Configuration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('masilia_ibexa_blocks');
        $root = $tree->getRootNode();

        // What changes from one site to the next (under `system`: a siteaccess or a siteaccess group).
        $this->generateScopeBaseNode($root)
            ->scalarNode('layout_remote_id')
                ->info('The remote id of the content that holds the layout of the site (logo, menus text, footer): one per site')
            ->end()
            ->variableNode('favicons')
                ->info('The icons of the site, over those of its theme: a folder of public/ (the icons it holds are linked) or a list of links {rel, href, sizes, type}')
            ->end()
            ->arrayNode('layout_menus')
                ->info('The type of menu of each part of the layout (the menu manager\'s types)')
                ->children()
                    ->scalarNode('header')->end()
                    ->scalarNode('footer')->end()
                    ->scalarNode('footer_bottom')->end()
                ->end()
            ->end()
            // No default in these nodes: a scope that does not say a setting leaves it to the scope below (siteaccess, group, default).
            ->append($this->model())
            ->append($this->themes())
            ->append($this->blocks())
            ->append($this->rateLimit())
            ->append($this->conventions())
            ->append($this->search())
            ->append($this->fields())
            ->append($this->forms())
            ->append($this->newsletter());

        return $tree;
    }

    private function limit(string $name, string $what): \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition
    {
        $node = (new \Symfony\Component\Config\Definition\Builder\TreeBuilder($name))->getRootNode();
        $node
            ->info(sprintf('%s: at most `limit` requests from one address in each `interval` ("10 minutes"); unset: not limited', $what))
            ->children()
                ->integerNode('limit')->isRequired()->min(1)->end()
                ->scalarNode('interval')->isRequired()->cannotBeEmpty()->end()
            ->end();

        return $node;
    }

    private function forms(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('forms'))->getRootNode();
        $node
                ->info('The forms of the site that visitors can submit by mail. Nothing is accepted for a content type not listed here.')
                ->children()
                    ->arrayNode('content_types')->info('Content types of the forms (a block that holds recipients and a sender)')->scalarPrototype()->end()->end()
                    ->scalarNode('recipients_field')->end()
                    ->scalarNode('sender_field')->end()
                    ->scalarNode('title_field')->end()
                    ->scalarNode('from_address')->info('Used when a form has no valid sender of its own')->end()
                    ->scalarNode('template')->info('Relative to the templates of the theme')->end()
                    ->integerNode('max_fields')->end()
                    ->integerNode('max_length')->end()
                    ->arrayNode('contact')
                        ->info('Contact forms: a block whose matrix of questions is a form on the page, mailed to the recipients the block names')
                        ->children()
                            ->arrayNode('content_types')->info('The block types that are contact forms, e.g. [form]')->scalarPrototype()->end()->end()
                            ->scalarNode('fields_field')->info('The matrix: label, type (text, email, textarea, select, radio, checkbox, tel, number, date, file), is_required, values')->end()
                            ->scalarNode('recipients_field')->end()
                            ->scalarNode('sender_field')->end()
                            ->scalarNode('success_field')->end()
                            ->scalarNode('error_field')->end()
                            ->scalarNode('template')->info('Mail to the recipients (blocks subject and body), relative to the templates of the theme')->end()
                            ->scalarNode('confirmation_template')->info('Mail to the visitor, when one answer is an email address')->end()
                            ->booleanNode('captcha')->info('Needs gregwar/captcha-bundle; ignored without it')->end()
                        ->end()
                    ->end()
                ->end();

        return $node;
    }

    private function newsletter(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('newsletter'))->getRootNode();
        $node
                ->info('Subscribing to a newsletter with a confirmation mail (POST /api/subscribe, /subscription/confirm/{token}, /subscription/unsubscribe/{token}). Needs doctrine/orm and the routes of routing_newsletter.yaml.')
                ->children()
                    ->booleanNode('enabled')->end()
                    ->scalarNode('from_address')->info('The confirmation mail is sent from it (forms.from_address when null)')->end()
                    ->scalarNode('subject')->end()
                    ->scalarNode('list_name')->info('What the visitor subscribed to, in the page that confirms it')->end()
                    ->scalarNode('confirmation_template')->info('Relative to the templates of the theme: has confirmUrl and subscriber')->end()
                    ->scalarNode('list_id')->info('The list of subscribers of the site: sites that give the same one share them, the others have their own (default: "default")')->end()
                    ->scalarNode('status_template')->info('The page of the links of the mails: has status (success|info|error), title and message')->end()
                ->end();

        return $node;
    }

    private function conventions(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('conventions'))->getRootNode();
        $node
                    ->info('How the sites of this project are built. The defaults are the Masilia way.')
                    ->children()
                        ->scalarNode('page_type')->info('The content type of a page')->end()
                        ->scalarNode('home_type')->info('The content type of a home page')->end()
                        ->scalarNode('site_type')->info('The content type of the container of a site')->end()
                        ->scalarNode('blocks_folder_suffix')->info('The end of the name of the folder that holds the blocks of a page: "Home Blocks"')->end()
                        ->scalarNode('folder_type')->info('The content type of a folder')->end()
                        ->scalarNode('blocks_field')->info('The field of a page that holds its blocks')->end()
                        ->scalarNode('items_field')->info('The field of a block that holds its child items')->end()
                        ->arrayNode('items_field_by_type')
                            ->info('The field that holds the child items of the block types that do not call it items_field: {info_cards: cards}')
                            ->useAttributeAsKey('type')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('block_group')->info('The group of content types that are blocks')->end()
                        ->arrayNode('content_groups')
                            ->info('The groups of the other content types the site is made of')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end();

        return $node;
    }

    private function search(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('search'))->getRootNode();
        $node
                    ->info('What the search of a site can do: the content types, the sorts, the facets. Nothing is searched that is not named here.')
                    ->children()
                        ->arrayNode('allowed_content_types')->info('The content types a request may search')->scalarPrototype()->end()->end()
                        ->arrayNode('default_content_types')->info('What is searched when a request names none')->scalarPrototype()->end()->end()
                        ->scalarNode('default_sort')->end()
                        ->arrayNode('sorts')
                            ->info('Sort key => {field: [content type, field], direction: asc|desc}; with no field, the date of publication (name: true sorts by name)')
                            ->useAttributeAsKey('key')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('field')->scalarPrototype()->end()->end()
                                    ->enumNode('direction')->values(['asc', 'desc'])->end()
                                    ->booleanNode('name')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('variations')->info('Field identifier => the image variation of the results')->useAttributeAsKey('field')->scalarPrototype()->end()->end()
                        ->arrayNode('facets')->info('Facet name => the field of the search index that holds its values')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
                        ->scalarNode('category_field')->end()
                        ->scalarNode('tags_field')->end()
                        ->arrayNode('page')
                            ->children()
                                ->arrayNode('content_types')->info('What the search page searches')->scalarPrototype()->end()->end()
                                ->integerNode('items')->end()
                                ->arrayNode('popular_searches')->scalarPrototype()->end()->end()
                            ->end()
                        ->end()
                        ->arrayNode('api')
                            ->children()
                                ->integerNode('default_items')->end()
                                ->integerNode('max_items')->end()
                                ->integerNode('max_term_length')->min(1)->info('A longer search term, category or tag is cut to this many characters')->end()
                                ->integerNode('max_tags')->min(0)->info('The tags of a request beyond this many are dropped')->end()
                                ->integerNode('suggestions_cache_ttl')->min(0)->info('Seconds the suggestions may be kept by the browser and the HTTP cache (0: not cached)')->end()
                            ->end()
                        ->end()
                        ->arrayNode('auto_item_sort_fields')->info('Content type => the field its latest items are sorted by')->useAttributeAsKey('type')->scalarPrototype()->end()->end()
                        ->scalarNode('auto_item_default_sort_field')->end()
                    ->end();

        return $node;
    }

    private function fields(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('fields'))->getRootNode();
        $node
                    ->info('How the values of fields reach the templates')
                    ->children()
                        ->enumNode('unknown_type')
                            ->values(['auto', 'error', 'null'])
                            ->info('A field type no renderer handles: "error" refuses it, "null" logs it and gives null, "auto" is "error" in debug and "null" otherwise')
                        ->end()
                        ->arrayNode('summary_types')
                            ->info('Content types that a relation field gives as {id, name} instead of a whole content (tags, categories)')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('ignored_types')
                            ->info('Field types that are never rendered (they give null, without a log)')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end();

        return $node;
    }

    private function model(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('model'))->getRootNode();
        $node
                    ->info('Where the content types of the project are (the socle of the package is always installed)')
                    ->children()
                        ->arrayNode('directories')
                            ->info('Directories of *.yaml content type definitions, besides config/content_model of the project. A package adds its own by prepending this setting.')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end();

        return $node;
    }

    private function themes(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('themes'))->getRootNode();
        $node
                    ->info('Where the themes are')
                    ->children()
                        ->arrayNode('directories')
                            ->info('Directories with one folder per theme, each with a theme.yaml: <directory>/<theme>/theme.yaml')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end();

        return $node;
    }

    private function blocks(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('blocks'))->getRootNode();
        $node
                    ->info('How blocks are shown')
                    ->children()
                        ->arrayNode('view_types')
                            ->info('The view types that this bundle shows for any content type that a theme.yaml names. A view the project configures itself (content_view) wins over it.')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('handled_by_project')
                            ->info('Block types whose view the project configures itself (content_view with a controller): the theme check does not expect a theme to show them')
                            ->scalarPrototype()->end()
                        ->end()
                        ->booleanNode('flatten_fields')
                            ->info('Also give each field to the template as a variable of its own (hero_banner.twig reads `title`), as well as in `fields`')
                        ->end()
                        ->arrayNode('lists')
                            ->info('Blocks that list contents, ready to use: name the block types and, if they differ from the Masilia ones, their fields. Nothing is on until its types are named.')
                            ->children()
                                ->arrayNode('auto_items')
                                    ->info('A block with no items whose source is "auto" lists the latest contents of a type chosen in its fields')
                                    ->children()
                                        ->arrayNode('types')->info('The block types, e.g. [grid_cards, slider]')->scalarPrototype()->end()->end()
                                        ->scalarNode('source_field')->info('A selection: the value below asks for the automatic list')->end()
                                        ->scalarNode('source_value')->end()
                                        ->scalarNode('type_field')->info('A selection: the content type to list')->end()
                                        ->scalarNode('count_field')->end()
                                        ->integerNode('default_count')->min(1)->end()
                                    ->end()
                                ->end()
                                ->arrayNode('featured')
                                    ->info('A block whose view type has a given value is shown by another template (the "Featured" grid as the latest news)')
                                    ->children()
                                        ->arrayNode('types')->info('The block types, e.g. [grid_cards]')->scalarPrototype()->end()->end()
                                        ->scalarNode('field')->end()
                                        ->scalarNode('value')->end()
                                        ->scalarNode('template')->info('Relative to the templates of a theme')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('all_posts')
                                    ->info('The block that lists the posts of the site with their filters (its first posts, its filters, the search app configuration)')
                                    ->children()
                                        ->arrayNode('types')->info('The block types, e.g. [all_posts]')->scalarPrototype()->end()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end();

        return $node;
    }

    private function rateLimit(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('rate_limit'))->getRootNode();
        $node
                    ->info('How often a visitor may use the public endpoints that send a mail (needs symfony/rate-limiter). Nothing is limited by default.')
                    ->children()
                        ->append($this->limit('forms_submit', 'POST /api/forms/submit'))
                        ->append($this->limit('subscribe', 'POST /api/subscribe'))
                    ->end();

        return $node;
    }
}
