<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Search;

/**
 * What the search of a site is allowed and set up to do. Everything that is a fact of one project (which content types people
 * search, how they are sorted, which index fields give the facets) is here, from the configuration, and nothing is written in
 * the code that searches.
 */
final readonly class SearchSettings
{
    /**
     * @param list<string>                       $allowedContentTypes The content types a request may search; any other is dropped
     * @param list<string>                       $defaultContentTypes What is searched when a request names none
     * @param list<string>                       $pageContentTypes    What the search page searches
     * @param array<string, SortSpec>            $sorts               Sort key => how it sorts
     * @param array<string, string>              $variations          Field identifier => image variation of the results
     * @param array<string, string>              $facets              Facet name => the field of the search index that holds its values
     * @param list<string>                       $popularSearches
     * @param array<string, string>              $autoItemSortFields  Content type => the field its latest items are sorted by
     * @param int                                $maxTermLength       A longer search term is cut to this length
     * @param int                                $maxTags             The tags of a request beyond this many are dropped
     * @param int                                $suggestionsCacheTtl Seconds the suggestions may be cached (0: not)
     */
    public function __construct(
        public array $allowedContentTypes = ['article'],
        public array $defaultContentTypes = ['article'],
        public array $pageContentTypes = ['article'],
        public int $pageItems = 10,
        public int $defaultItems = 12,
        public int $maxItems = 50,
        public string $defaultSort = 'published_desc',
        public array $sorts = [],
        public array $variations = [],
        public array $facets = [],
        public string $categoryField = 'category',
        public string $tagsField = 'tags',
        public array $popularSearches = [],
        public array $autoItemSortFields = [],
        public string $autoItemDefaultSortField = 'title',
        public int $maxTermLength = 200,
        public int $maxTags = 10,
        public int $suggestionsCacheTtl = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $settings The "search" node of the bundle configuration
     */
    public static function fromSettings(array $settings): self
    {
        $d = new self();
        $sorts = [];
        foreach ((array) ($settings['sorts'] ?? []) as $key => $spec) {
            $sorts[(string) $key] = SortSpec::fromArray((string) $key, (array) $spec);
        }
        // Without sorts of its own a site sorts by date of publication and by name.
        $sorts = $sorts ?: [
            'published_desc' => new SortSpec(null, false),
            'published_asc' => new SortSpec(null, true),
            'title_asc' => new SortSpec(null, true, true),
            'title_desc' => new SortSpec(null, false, true),
        ];

        $allowed = array_values(array_map('strval', (array) ($settings['allowed_content_types'] ?? $d->allowedContentTypes)));
        $default = array_values(array_map('strval', (array) ($settings['default_content_types'] ?? $allowed)));
        $page = array_values(array_map('strval', (array) ($settings['page']['content_types'] ?? $default)));

        return new self(
            allowedContentTypes: array_values(array_unique([...$allowed, ...$default, ...$page])),
            defaultContentTypes: $default,
            pageContentTypes: $page,
            pageItems: (int) ($settings['page']['items'] ?? $d->pageItems),
            defaultItems: (int) ($settings['api']['default_items'] ?? $d->defaultItems),
            maxItems: (int) ($settings['api']['max_items'] ?? $d->maxItems),
            defaultSort: (string) ($settings['default_sort'] ?? $d->defaultSort),
            sorts: $sorts,
            variations: array_map('strval', (array) ($settings['variations'] ?? [])),
            facets: array_map('strval', (array) ($settings['facets'] ?? [])),
            categoryField: (string) ($settings['category_field'] ?? $d->categoryField),
            tagsField: (string) ($settings['tags_field'] ?? $d->tagsField),
            popularSearches: array_values(array_map('strval', (array) ($settings['page']['popular_searches'] ?? []))),
            autoItemSortFields: array_map('strval', (array) ($settings['auto_item_sort_fields'] ?? [])),
            autoItemDefaultSortField: (string) ($settings['auto_item_default_sort_field'] ?? $d->autoItemDefaultSortField),
            maxTermLength: max(1, (int) ($settings['api']['max_term_length'] ?? $d->maxTermLength)),
            maxTags: max(0, (int) ($settings['api']['max_tags'] ?? $d->maxTags)),
            suggestionsCacheTtl: max(0, (int) ($settings['api']['suggestions_cache_ttl'] ?? $d->suggestionsCacheTtl)),
        );
    }

    public function sort(string $key): SortSpec
    {
        return $this->sorts[$key] ?? $this->sorts[$this->defaultSort] ?? new SortSpec(null, false);
    }
}
