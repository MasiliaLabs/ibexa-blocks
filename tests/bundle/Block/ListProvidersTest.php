<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Block;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Masilia\Bundle\IbexaBlocks\Block\Provider\AutoItemsParameters;
use Masilia\Bundle\IbexaBlocks\Block\Provider\FeaturedParameters;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Search\ContentLister;
use Masilia\IbexaBlocks\Block\ViewState;
use PHPUnit\Framework\TestCase;

final class ListProvidersTest extends TestCase
{
    private const AUTO = ['source_field' => 'item_source', 'source_value' => 'auto', 'type_field' => 'item_type', 'count_field' => 'items_count', 'default_count' => 3];

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

    /** @param array<string, mixed> $more */
    private function settings(array $more = []): \Masilia\Bundle\IbexaBlocks\Site\SettingsProvider
    {
        $p = ['masilia_ibexa_blocks:blocks.lists.auto_items.types' => ['grid_cards'], 'masilia_ibexa_blocks:blocks.lists.featured.types' => ['grid_cards']];
        foreach ($more as $name => $value) {
            $p['masilia_ibexa_blocks:' . $name] = $value;
        }

        return TestSettings::fromParameters($p);
    }

    private function block(string $type = 'grid_cards'): RenderableContent
    {
        $block = (new \ReflectionClass(RenderableContent::class))->newInstanceWithoutConstructor();
        $content = $this->createStub(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $content->method('getContentType')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\ContentType(['identifier' => $type]));
        (new \ReflectionProperty(RenderableContent::class, 'content'))->setValue($block, $content);

        return $block;
    }

    public function testABlockWithNoItemsAndAnAutomaticSourceListsTheLatestOfItsType(): void
    {
        $provider = new AutoItemsParameters($this->lister(), $this->settings());

        $parameters = $provider->parameters($this->block(), ['items' => [], 'item_source' => ['auto'], 'item_type' => ['article'], 'items_count' => 6], new ViewState('blocks'));

        self::assertSame(['items'], array_keys($parameters));
        self::assertSame([['article', 6]], $this->asked);
    }

    public function testTheDefaultCountIsUsedWhenTheBlockHasNone(): void
    {
        $provider = new AutoItemsParameters($this->lister(), $this->settings());

        $provider->parameters($this->block(), ['item_source' => ['auto'], 'item_type' => ['event']], new ViewState('blocks'));

        self::assertSame([['event', 3]], $this->asked);
    }

    public function testItemsOfItsOwnAManualSourceOrNoTypeLeaveTheBlockAlone(): void
    {
        $provider = new AutoItemsParameters($this->lister(), $this->settings());
        $view = new ViewState('blocks');

        self::assertSame([], $provider->parameters($this->block(), ['items' => ['x'], 'item_source' => ['auto'], 'item_type' => ['article']], $view));
        self::assertSame([], $provider->parameters($this->block(), ['item_source' => ['manual'], 'item_type' => ['article']], $view));
        self::assertSame([], $provider->parameters($this->block(), ['item_source' => ['auto']], $view));
        self::assertSame([], $this->asked);
    }

    public function testTheFieldsAreTheSettingsOnes(): void
    {
        $provider = new AutoItemsParameters($this->lister(), $this->settings(['conventions.items_field' => 'cards', 'blocks.lists.auto_items.source_field' => 'from', 'blocks.lists.auto_items.source_value' => 'latest', 'blocks.lists.auto_items.type_field' => 'what', 'blocks.lists.auto_items.count_field' => 'how_many', 'blocks.lists.auto_items.default_count' => 2]));

        $parameters = $provider->parameters($this->block(), ['cards' => [], 'from' => ['latest'], 'what' => ['post'], 'how_many' => 4], new ViewState('blocks'));

        self::assertSame(['cards'], array_keys($parameters));
        self::assertSame([['post', 4]], $this->asked);
    }

    public function testAFeaturedBlockIsShownByTheTemplateOfTheSettings(): void
    {
        $provider = new FeaturedParameters($this->settings());

        $featured = new ViewState('blocks');
        self::assertSame([], $provider->parameters($this->block(), ['view_type' => ['Featured']], $featured));
        self::assertSame('blocks/latest_news.twig', $featured->template());

        $plain = new ViewState('blocks');
        $provider->parameters($this->block(), ['view_type' => ['Grid']], $plain);
        self::assertNull($plain->template());
    }

    public function testASiteAnswersOnlyForTheBlockTypesItNames(): void
    {
        $auto = new AutoItemsParameters($this->lister(), $this->settings());
        $none = new AutoItemsParameters($this->lister(), TestSettings::fromParameters([]));
        $fields = ['items' => [], 'item_source' => ['auto'], 'item_type' => ['article'], 'items_count' => 6];

        self::assertSame(['items'], array_keys($auto->parameters($this->block(), $fields, new ViewState('blocks'))));
        self::assertSame([], $auto->parameters($this->block('slider'), $fields, new ViewState('blocks')), 'a type the site does not name');
        self::assertSame([], $none->parameters($this->block(), $fields, new ViewState('blocks')), 'a site that names none');

        $state = new ViewState('blocks');
        (new FeaturedParameters(TestSettings::fromParameters([])))->parameters($this->block(), ['view_type' => ['Featured']], $state);
        self::assertNull($state->template(), 'the featured template is only for the sites that turn it on');
    }
}
