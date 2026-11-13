<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Search;

use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService as IbexaSearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\IbexaBlocks\Search\SearchSettings;

/**
 * The latest contents of a type on the site, newest first by the field the settings name for the type (the date of an article,
 * of an event, the name of a team member): what a grid or a slider that fills itself shows.
 */
final readonly class LatestContents implements ContentLister
{
    public function __construct(
        private IbexaSearchService $search,
        private LocationService $locations,
        private ContentFactory $factory,
        private ConfigResolverInterface $config,
        private SearchSettings $settings,
    ) {
    }

    /**
     * @return list<RenderableContent>
     */
    public function of(string $contentType, int $limit): array
    {
        $root = $this->locations->loadLocation((int) $this->config->getParameter('content.tree_root.location_id'));
        $field = $this->settings->autoItemSortFields[$contentType] ?? $this->settings->autoItemDefaultSortField;

        $result = $this->search->findLocations(new LocationQuery([
            'filter' => new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier([$contentType]),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Subtree($root->pathString),
            ]),
            'sortClauses' => [new SortClause\Field($contentType, $field, Query::SORT_DESC)],
            'limit' => $limit,
        ]));

        $items = [];
        foreach ($result->searchHits as $hit) {
            $location = $hit->valueObject;
            $items[] = $this->factory->fromContent($location->getContent(), $location);
        }

        return $items;
    }
}
