<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block\Provider;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Search\SearchService;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The block that lists the posts of the site with their filters: its first posts, the values of its filters, and the settings
 * of the search app that takes over in the browser (`search_config`). The block has the fields content_types, items_per_page,
 * default_sort and the enable_* / show_* switches; it is a block type of the project, named in
 * masilia_ibexa_blocks.blocks.lists.all_posts.types.
 */
final readonly class AllPostsParameters implements BlockViewParameters
{
    public function __construct(
        private SearchService $search,
        private UrlGeneratorInterface $urls,
        private SettingsProvider $provider,
    ) {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        if (!in_array($block->type(), $this->provider->listTypes('all_posts'), true)) {
            return [];
        }

        $subtree = (string) ($view->parameters['parentSubtree'] ?? '');
        $contentTypes = array_values((array) ($fields['content_types'] ?? ['article']));
        $defaultSort = array_values((array) ($fields['default_sort'] ?? []))[0] ?? 'published_desc';

        $initial = $this->search->initial($contentTypes, (int) ($fields['items_per_page'] ?? 9), $defaultSort, $subtree);

        return [
            'posts' => $initial['posts'],
            'categories' => $initial['categories'] ?? [],
            'all_tags' => $initial['tags'] ?? [],
            'total_count' => $initial['total'],
            'search_config' => [
                'contentTypes' => $fields['content_types'] ?? ['article'],
                'itemsPerPage' => $fields['items_per_page'] ?? 12,
                'totalPosts' => $initial['total'],
                'defaultSort' => $defaultSort,
                'enableSearch' => $fields['enable_search'] ?? true,
                'enableCategoryFilter' => $fields['enable_category_filter'] ?? true,
                'enableTagFilter' => $fields['enable_tag_filter'] ?? true,
                'enableAuthorFilter' => $fields['enable_author_filter'] ?? false,
                'enableDateFilter' => $fields['enable_date_filter'] ?? false,
                'layoutColumns' => array_keys((array) ($fields['layout_columns'] ?? []))[0] ?? '1',
                'showExcerpts' => $fields['show_excerpts'] ?? true,
                'showAuthorInfo' => $fields['show_author_info'] ?? true,
                'showPublishDate' => $fields['show_publish_date'] ?? true,
                'showTags' => $fields['show_tags'] ?? true,
                'showCategories' => $fields['show_categories'] ?? true,
                'apiEndpoint' => $this->urls->generate('api_posts_search'),
                'parentSubtree' => $subtree,
            ],
        ];
    }
}
