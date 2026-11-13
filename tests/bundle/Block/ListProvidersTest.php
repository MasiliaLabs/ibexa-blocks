<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Block;

use Masilia\Bundle\IbexaBlocks\Block\Provider\AutoItemsParameters;
use Masilia\Bundle\IbexaBlocks\Block\Provider\FeaturedParameters;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Search\ContentLister;
use Masilia\IbexaBlocks\Block\ViewState;
use PHPUnit\Framework\TestCase;

final class ListProvidersTest extends TestCase
{
    private const AUTO = ['items_field' => 'items', 'source_field' => 'item_source', 'source_value' => 'auto', 'type_field' => 'item_type', 'count_field' => 'items_count', 'default_count' => 3];

    /** @var list<array{string, int}> */
    private array $asked = [];

    private function lister(): ContentLister
    {
        return new class($this->asked) implements ContentLister {
            public function __construct(private array &$asked) {}

            public function of(string $contentType, int $limit): array
            {
                $this->asked[] = [$contentType, $limit];

                return [(object) ['type' => $contentType]];
            }
        };
    }

    private function block(): RenderableContent
    {
        return (new \ReflectionClass(RenderableContent::class))->newInstanceWithoutConstructor();
    }

    public function testABlockWithNoItemsAndAnAutomaticSourceListsTheLatestOfItsType(): void
    {
        $provider = new AutoItemsParameters($this->lister(), self::AUTO);

        $parameters = $provider->parameters($this->block(), ['items' => [], 'item_source' => ['auto'], 'item_type' => ['article'], 'items_count' => 6], new ViewState('blocks'));

        self::assertSame(['items'], array_keys($parameters));
        self::assertSame([['article', 6]], $this->asked);
    }

    public function testTheDefaultCountIsUsedWhenTheBlockHasNone(): void
    {
        $provider = new AutoItemsParameters($this->lister(), self::AUTO);

        $provider->parameters($this->block(), ['item_source' => ['auto'], 'item_type' => ['event']], new ViewState('blocks'));

        self::assertSame([['event', 3]], $this->asked);
    }

    public function testItemsOfItsOwnAManualSourceOrNoTypeLeaveTheBlockAlone(): void
    {
        $provider = new AutoItemsParameters($this->lister(), self::AUTO);
        $view = new ViewState('blocks');

        self::assertSame([], $provider->parameters($this->block(), ['items' => ['x'], 'item_source' => ['auto'], 'item_type' => ['article']], $view));
        self::assertSame([], $provider->parameters($this->block(), ['item_source' => ['manual'], 'item_type' => ['article']], $view));
        self::assertSame([], $provider->parameters($this->block(), ['item_source' => ['auto']], $view));
        self::assertSame([], $this->asked);
    }

    public function testTheFieldsAreTheSettingsOnes(): void
    {
        $provider = new AutoItemsParameters($this->lister(), ['items_field' => 'cards', 'source_field' => 'from', 'source_value' => 'latest', 'type_field' => 'what', 'count_field' => 'how_many', 'default_count' => 2]);

        $parameters = $provider->parameters($this->block(), ['cards' => [], 'from' => ['latest'], 'what' => ['post'], 'how_many' => 4], new ViewState('blocks'));

        self::assertSame(['cards'], array_keys($parameters));
        self::assertSame([['post', 4]], $this->asked);
    }

    public function testAFeaturedBlockIsShownByTheTemplateOfTheSettings(): void
    {
        $provider = new FeaturedParameters(['field' => 'view_type', 'value' => 'Featured', 'template' => 'blocks/latest_news.twig']);

        $featured = new ViewState('blocks');
        self::assertSame([], $provider->parameters($this->block(), ['view_type' => ['Featured']], $featured));
        self::assertSame('blocks/latest_news.twig', $featured->template());

        $plain = new ViewState('blocks');
        $provider->parameters($this->block(), ['view_type' => ['Grid']], $plain);
        self::assertNull($plain->template());
    }
}
