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
            term: mb_substr(trim((string) ($data['search'] ?? '')), 0, $settings->maxTermLength),
            category: isset($data['category']) ? mb_substr(trim((string) $data['category']), 0, $settings->maxTermLength) : 'All',
            tags: is_array($tags) ? array_slice(array_values(array_filter(array_map(static fn (mixed $tag): string => mb_substr(is_scalar($tag) ? trim((string) $tag) : '', 0, $settings->maxTermLength), array_values($tags)), static fn (string $tag): bool => $tag !== '')), 0, $settings->maxTags) : [],
            page: max(1, (int) ($data['page'] ?? 1)),
            limit: min($settings->maxItems, max(1, (int) ($data['limit'] ?? $settings->defaultItems))),
            sort: isset($settings->sorts[(string) ($data['sort'] ?? '')]) ? (string) $data['sort'] : $settings->defaultSort,
            parentSubtree: trim((string) ($data['parent_subtree'] ?? '')),
        );
    }
}
