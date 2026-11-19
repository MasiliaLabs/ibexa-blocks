<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Search;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchResult;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition as FieldDefinitionContract;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Search\LatestContents;
use Masilia\IbexaBlocks\Search\SearchSettings;
use PHPUnit\Framework\TestCase;

final class LatestContentsTest extends TestCase
{
    private function sortUsed(?FieldDefinitionContract $definition, bool $typeExists = true): SortClause
    {
        $query = null;
        $search = $this->createMock(SearchService::class);
        $search->method('findLocations')->willReturnCallback(static function (LocationQuery $q) use (&$query): SearchResult {
            $query = $q;

            return new SearchResult(['searchHits' => []]);
        });
        $location = $this->createMock(Location::class);
        $location->method('__get')->willReturn('/1/2/');
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturn($location);
        $type = $this->createMock(ContentType::class);
        $type->method('getFieldDefinition')->willReturn($definition);
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturnCallback(static fn () => $typeExists ? $type : throw new NotFoundException('type', 'x'));
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn(2);
        $factory = (new \ReflectionClass(ContentFactory::class))->newInstanceWithoutConstructor();

        (new LatestContents($search, $locations, $factory, $config, new SearchSettings(), $types))->of('partner', 6);

        return $query->sortClauses[0];
    }

    private function field(bool $searchable): FieldDefinitionContract
    {
        return new FieldDefinition(['isSearchable' => $searchable]);
    }

    public function testASearchableFieldOfTheTypeIsTheSort(): void
    {
        self::assertInstanceOf(SortClause\Field::class, $this->sortUsed($this->field(true)));
    }

    public function testATypeWithoutThatFieldIsSortedByThePublicationDate(): void
    {
        self::assertInstanceOf(SortClause\DatePublished::class, $this->sortUsed(null));
    }

    public function testAFieldThatCannotBeSearchedOrAMissingTypeFallsBackToo(): void
    {
        self::assertInstanceOf(SortClause\DatePublished::class, $this->sortUsed($this->field(false)));
        self::assertInstanceOf(SortClause\DatePublished::class, $this->sortUsed(null, false));
    }
}
