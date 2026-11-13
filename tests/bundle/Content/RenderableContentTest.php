<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Value\Link;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RenderableContentTest extends TestCase
{
    use Fixtures;

    /** @var list<string> */
    private array $tagged = [];
    private int $linksMade = 0;
    private int $trailsMade = 0;

    private function services(): RenderableServices
    {
        $links = new class($this->linksMade) implements LinkProvider {
            public function __construct(private int &$made) {}
            public function forLocation(Location $location): Link { ++$this->made; return new Link('/p/' . $location->getId(), 'Name', ['identifier' => 'page']); }
            public function urlForLocation(int $locationId): string { return '/p/' . $locationId; }
        };
        $breadcrumbs = new class($this->trailsMade) implements BreadcrumbProvider {
            public function __construct(private int &$made) {}
            public function forLocation(Location $location): array { ++$this->made; return [new Link('/', 'Home')]; }
        };
        $tagger = new class($this->tagged) implements CacheTagger {
            public function __construct(private array &$tagged) {}
            public function tagContents(array $contentIds): void { $this->tagged[] = 'content:' . implode(',', $contentIds); }
            public function tagLocations(array $locationIds): void { $this->tagged[] = 'location:' . implode(',', $locationIds); }
        };
        $adminMeta = new class() implements AdminMetaProvider {
            public function forContent(Content $content): array { return ['content_id' => $content->getContentInfo()->getId()]; }
        };

        return new RenderableServices(new FieldRendererRegistry(new ServiceLocator([])), $links, $breadcrumbs, $adminMeta, $tagger);
    }

    private function location(int $id): Location
    {
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn($id);

        return $location;
    }

    public function testItKnowsItsIdWithoutLoadingAnything(): void
    {
        $loads = 0;
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturnCallback(function () use (&$loads) { ++$loads; });
        $factory = new ContentFactory($contents, $this->createMock(LocationService::class), $this->services());

        $renderable = $factory->fromContentId(42);

        self::assertSame(42, $renderable->id());
        self::assertSame(0, $loads);
        self::assertSame([], $this->tagged);
    }

    public function testReadingTheContentLoadsItOnceAndTagsTheResponseOnce(): void
    {
        $content = $this->content($this->type('page', []), [], 42, 'About');
        $contents = $this->createMock(ContentService::class);
        $contents->expects(self::once())->method('loadContent')->with(42)->willReturn($content);
        $mainLocation = $this->location(9);
        $content->getContentInfo()->method('getMainLocation')->willReturn($mainLocation);
        $factory = new ContentFactory($contents, $this->createMock(LocationService::class), $this->services());

        $renderable = $factory->fromContentId(42);

        self::assertSame('About', $renderable->name());
        self::assertSame('page', $renderable->type());
        self::assertSame('About', $renderable->name());
        self::assertSame(['content:42'], $this->tagged);
    }

    public function testEachPartIsMadeWhenAskedAndKept(): void
    {
        $content = $this->content($this->type('page', []), [], 42);
        $content->getContentInfo()->method('getMainLocation')->willReturn($this->location(9));
        $factory = new ContentFactory($this->createMock(ContentService::class), $this->createMock(LocationService::class), $this->services());
        $renderable = $factory->fromContent($content);

        self::assertSame(0, $this->linksMade + $this->trailsMade, 'nothing is made until it is asked for');
        self::assertSame('/p/9', $renderable->url());
        self::assertSame('/p/9', $renderable->link()->uri);
        $renderable->link();
        $renderable->breadcrumb();
        $renderable->breadcrumb();

        self::assertSame(1, $this->linksMade);
        self::assertSame(1, $this->trailsMade);
        self::assertSame(['content_id' => 42], $renderable->adminMeta());
        self::assertSame(9, $renderable->locationId());
    }

    public function testAContentWithNoLocationHasNoUrlLinkOrBreadcrumb(): void
    {
        $content = $this->content($this->type('block', []), [], 5);
        $content->getContentInfo()->method('getMainLocation')->willReturn(null);
        $renderable = (new ContentFactory($this->createMock(ContentService::class), $this->createMock(LocationService::class), $this->services()))->fromContent($content);

        self::assertSame('', $renderable->url());
        self::assertNull($renderable->link());
        self::assertSame([], $renderable->breadcrumb());
    }

    public function testFromALocationIdTheLocationIsKnownAndTheContentComesFromIt(): void
    {
        $content = $this->content($this->type('page', []), [], 42, 'From a location');
        $location = $this->location(9);
        $location->method('getContent')->willReturn($content);
        $locations = $this->createMock(LocationService::class);
        $locations->expects(self::once())->method('loadLocation')->with(9)->willReturn($location);
        $renderable = (new ContentFactory($this->createMock(ContentService::class), $locations, $this->services()))->fromLocationId(9);

        self::assertSame(9, $renderable->locationId());
        self::assertSame('/p/9', $renderable->url(), 'the url needs only the location id');
        self::assertSame('From a location', $renderable->name());
        self::assertSame(42, $renderable->id());
        $tags = $this->tagged;
        sort($tags);
        self::assertSame(['content:42', 'location:9'], $tags, 'the location and the content are tagged, each once');
    }
}
