<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Search;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService as IbexaSearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Aggregation;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchResult;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\IbexaBlocks\Search\SearchRequest;
use Masilia\IbexaBlocks\Search\SearchSettings;

/**
 * Searches the contents of the current site and gives them as a template or a script needs them: the fields of each result
 * (images at the variation the settings name), its type, its link and its admin meta, with the paging. It searches the tree of the
 * site only, and only the content types the settings allow.
 */
final readonly class SearchService
{
    public function __construct(
        private IbexaSearchService $search,
        private ContentService $contents,
        private LocationService $locations,
        private ContentFactory $factory,
        private ConfigResolverInterface $config,
        private SettingsProvider $provider,
    ) {
    }

    /**
     * @return array{posts: list<array<string, mixed>>, total: int, page: int, limit: int, total_pages: int|float, has_next: bool, has_prev: bool}
     */
    public function search(SearchRequest $request): array
    {
        try {
            $result = $this->search->findContent($this->query($request));
        } catch (InvalidArgumentException) {
            $result = null;
        }

        $posts = [];
        foreach ($result?->searchHits ?? [] as $hit) {
            $content = $this->factory->fromContent($hit->valueObject);
            $link = $content->link();
            $posts[] = $content->fields()->all($this->settings()->variations) + [
                'content_type' => $content->type(),
                'link' => ['uri' => $link?->getUri(), 'name' => $link?->getName()],
                'admin_meta' => $content->adminMeta(),
            ];
        }

        $total = $result?->totalCount ?? 0;
        $pages = ceil($total / $request->limit);

        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $request->page,
            'limit' => $request->limit,
            'total_pages' => $pages,
            'has_next' => $request->page < $pages,
            'has_prev' => $request->page > 1,
        ];
    }

    /**
     * The values of each facet the settings define, for the contents of some types: [facet => [content id => name]].
     *
     * @param list<string> $contentTypes
     *
     * @return array<string, array<int|string, string>>
     */
    public function facets(array $contentTypes, string $parentSubtree = ''): array
    {
        if ($this->settings()->facets === []) {
            return [];
        }

        $aggregations = [];
        foreach ($this->settings()->facets as $name => $indexField) {
            $aggregation = new Aggregation\RawTermAggregation($name, $indexField);
            $aggregation->setLimit(50);
            $aggregation->setMinCount(1);
            $aggregations[] = $aggregation;
        }

        $result = $this->search->findContent(new Query([
            'filter' => new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier(array_values(array_intersect($contentTypes, $this->settings()->allowedContentTypes)) ?: $this->settings()->defaultContentTypes),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Subtree($this->subtree($parentSubtree)),
            ]),
            'aggregations' => $aggregations,
            'limit' => 0,
        ]));

        $facets = [];
        foreach (array_keys($this->settings()->facets) as $name) {
            $facets[$name] = $this->values($result, $name);
        }

        return $facets;
    }

    /** What a page of posts needs to start with: its first results and the values of its filters. */
    public function initial(array $contentTypes, int $itemsPerPage, string $sort, string $parentSubtree = ''): array
    {
        $request = new SearchRequest($contentTypes === [] ? $this->settings()->defaultContentTypes : $contentTypes, limit: $itemsPerPage, sort: isset($this->settings()->sorts[$sort]) ? $sort : $this->settings()->defaultSort, parentSubtree: $parentSubtree);
        $posts = $this->search($request);

        return ['posts' => $posts['posts'], 'total' => $posts['total']] + $this->facets($contentTypes, $parentSubtree);
    }

    private function query(SearchRequest $request): Query
    {
        $criteria = [
            new Criterion\Subtree($this->subtree($request->parentSubtree)),
            new Criterion\ContentTypeIdentifier($request->contentTypes),
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
        ];
        if ($request->term !== '') {
            $criteria[] = new Criterion\FullText($request->term);
        }
        if ($request->category !== '' && $request->category !== 'All') {
            $criteria[] = new Criterion\Field($this->settings()->categoryField, Criterion\Operator::EQ, $request->category);
        }
        if ($request->tags !== []) {
            $criteria[] = new Criterion\LogicalAnd(array_map(
                fn (string $tag): Criterion => new Criterion\Field($this->settings()->tagsField, Criterion\Operator::CONTAINS, $tag),
                $request->tags,
            ));
        }

        return new Query([
            'filter' => new Criterion\LogicalAnd($criteria),
            'sortClauses' => [$this->sortClause($request->sort)],
            'offset' => ($request->page - 1) * $request->limit,
            'limit' => $request->limit,
        ]);
    }

    private function sortClause(string $key): SortClause
    {
        $sort = $this->settings()->sort($key);
        $direction = $sort->ascending ? Query::SORT_ASC : Query::SORT_DESC;

        return match (true) {
            $sort->field !== null => new SortClause\Field($sort->field[0], $sort->field[1], $direction),
            $sort->byName => new SortClause\ContentName($direction),
            default => new SortClause\DatePublished($direction),
        };
    }

    /**
     * The tree that is searched: the site's own, or a part of it. A subtree outside the site is not honoured.
     */
    private function subtree(string $requested): string
    {
        $root = $this->locations->loadLocation((int) $this->config->getParameter('content.tree_root.location_id'))->pathString;

        return $requested !== '' && str_starts_with($requested, $root) ? $requested : $root;
    }

    /**
     * @return array<int|string, string> The name of each taxonomy content a facet has, by its content id
     */
    private function values(SearchResult $result, string $name): array
    {
        $values = [];
        $aggregation = $result->aggregations->toArray()[$name] ?? null;
        foreach ($aggregation !== null && method_exists($aggregation, 'getEntries') ? $aggregation->getEntries() : [] as $entry) {
            $key = $entry->getKey();
            if (!empty($key) && $key !== 'All' && $entry->getCount() > 0) {
                try {
                    $values[$key] = $this->contents->loadContent((int) $key)->getContentInfo()->getName();
                } catch (NotFoundException|UnauthorizedException) {
                    // A value whose content is gone or unreadable is not offered.
                }
            }
        }

        return array_unique($values);
    }

    private function settings(): SearchSettings
    {
        return $this->provider->search();
    }
}
