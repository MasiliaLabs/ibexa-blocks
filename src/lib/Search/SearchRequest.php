<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Search;

/**
 * What a visitor asked the search for, checked against what the site allows: only the content types it lets be searched, a
 * page size within bounds, a sort it knows. Whatever else the request said is dropped, not trusted.
 */
final readonly class SearchRequest
{
    /**
     * @param list<string> $contentTypes
     * @param list<string> $tags
     */
    public function __construct(
        public array $contentTypes,
        public string $term = '',
        public string $category = 'All',
        public array $tags = [],
        public int $page = 1,
        public int $limit = 12,
        public string $sort = 'published_desc',
        public string $parentSubtree = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data A decoded request body or query
     */
    public static function fromArray(array $data, SearchSettings $settings): self
    {
        $types = $data['content_types'] ?? $settings->defaultContentTypes;
        $types = array_values(array_intersect(array_map('strval', is_array($types) ? $types : [$types]), $settings->allowedContentTypes));

        $tags = $data['tags'] ?? [];

        return new self(
            contentTypes: $types === [] ? $settings->defaultContentTypes : $types,
            term: trim((string) ($data['search'] ?? '')),
            category: isset($data['category']) ? trim((string) $data['category']) : 'All',
            tags: is_array($tags) ? array_values(array_map('strval', $tags)) : [],
            page: max(1, (int) ($data['page'] ?? 1)),
            limit: min($settings->maxItems, max(1, (int) ($data['limit'] ?? $settings->defaultItems))),
            sort: isset($settings->sorts[(string) ($data['sort'] ?? '')]) ? (string) $data['sort'] : $settings->defaultSort,
            parentSubtree: trim((string) ($data['parent_subtree'] ?? '')),
        );
    }
}
