<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;

/**
 * Makes the renderable content a template reads, from what the application has: a content, a content id or a location id.
 * Whatever it loads, it tags the response for the HTTP cache with, the first time and only then: a page is purged when
 * something it was made from changes.
 */
final readonly class ContentFactory
{
    public function __construct(
        private ContentService $contents,
        private LocationService $locations,
        private RenderableServices $services,
    ) {
    }

    public function fromContent(Content $content, ?Location $location = null): RenderableContent
    {
        return new RenderableContent(
            $content->getContentInfo()->getId(),
            $location?->getId(),
            fn (): Content => $this->tagContent($content),
            fn (): ?Location => $this->tagLocation($location ?? $content->getContentInfo()->getMainLocation()),
            $this->services,
        );
    }

    /** Nothing is loaded until a template reads something other than the id. */
    public function fromContentId(int $contentId): RenderableContent
    {
        $content = null;
        $loadContent = function () use ($contentId, &$content): Content {
            return $content ??= $this->tagContent($this->contents->loadContent($contentId));
        };

        return new RenderableContent(
            $contentId,
            null,
            $loadContent,
            fn (): ?Location => $this->tagLocation($loadContent()->getContentInfo()->getMainLocation()),
            $this->services,
        );
    }

    /** Nothing is loaded until a template reads something other than the location id. */
    public function fromLocationId(int $locationId): RenderableContent
    {
        $location = null;
        $loadLocation = function () use ($locationId, &$location): Location {
            return $location ??= $this->tagLocation($this->locations->loadLocation($locationId));
        };

        return new RenderableContent(
            null,
            $locationId,
            fn (): Content => $this->tagContent($loadLocation()->getContent()),
            $loadLocation,
            $this->services,
        );
    }

    private function tagContent(Content $content): Content
    {
        $this->services->tagger->tagContents([$content->getContentInfo()->getId()]);

        return $content;
    }

    private function tagLocation(?Location $location): ?Location
    {
        if ($location !== null) {
            $this->services->tagger->tagLocations([$location->getId()]);
        }

        return $location;
    }
}
