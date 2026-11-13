<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaBlocks\Value\Link;

/**
 * A content as a template sees it: `page.name()`, `page.url`, `page.fields.title`, `page.breadcrumb`.
 *
 * It is cheap until it is read: it knows its id without loading anything, and each part (the content, its location, its
 * URL, its breadcrumb, its fields) is loaded by itself the first time a template asks for it, and then kept. What is
 * loaded tags the response for the HTTP cache (the factory's loaders do it), once.
 */
final class RenderableContent
{
    private ?Content $content = null;
    private ?Location $location = null;
    private bool $locationLoaded = false;
    private ?ContentFields $fields = null;
    private ?string $url = null;
    private ?Link $link = null;
    /** @var list<Link>|null */
    private ?array $breadcrumb = null;
    /** @var array<string, mixed>|null */
    private ?array $adminMeta = null;

    /**
     * @param \Closure(): Content        $loadContent
     * @param \Closure(): (Location|null) $loadLocation
     */
    public function __construct(
        private ?int $id,
        private ?int $locationId,
        private readonly \Closure $loadContent,
        private readonly \Closure $loadLocation,
        private readonly RenderableServices $services,
    ) {
    }

    public function id(): int
    {
        return $this->id ??= $this->content()->getContentInfo()->getId();
    }

    public function locationId(): ?int
    {
        return $this->locationId ??= $this->location()?->getId();
    }

    /** The content of the Ibexa repository, for what the rendering does not cover. */
    public function content(): Content
    {
        if ($this->content === null) {
            $this->content = ($this->loadContent)();
        }

        return $this->content;
    }

    public function location(): ?Location
    {
        if (!$this->locationLoaded) {
            $this->locationLoaded = true;
            $this->location = ($this->loadLocation)();
        }

        return $this->location;
    }

    public function name(): string
    {
        return $this->content()->getContentInfo()->getName();
    }

    /** The identifier of the content type. */
    public function type(): string
    {
        return $this->content()->getContentType()->identifier;
    }

    public function creationDate(): ?\DateTimeInterface
    {
        return $this->content()->getContentInfo()->publishedDate;
    }

    public function modificationDate(): ?\DateTimeInterface
    {
        return $this->content()->getContentInfo()->modificationDate;
    }

    public function isHidden(): bool
    {
        return $this->content()->getContentInfo()->isHidden();
    }

    public function isTrashed(): bool
    {
        return $this->content()->getContentInfo()->isTrashed();
    }

    /** The path of the page, or an empty string for a content that has no location. */
    public function url(): string
    {
        $locationId = $this->locationId();

        return $this->url ??= $locationId === null ? '' : $this->services->links->urlForLocation($locationId);
    }

    public function link(): ?Link
    {
        $location = $this->location();

        return $this->link ??= $location === null ? null : $this->services->links->forLocation($location);
    }

    /**
     * @return list<Link> From the root of the site to this page; empty for a content that has no location
     */
    public function breadcrumb(): array
    {
        $location = $this->location();

        return $this->breadcrumb ??= $location === null ? [] : $this->services->breadcrumbs->forLocation($location);
    }

    /**
     * What the editing toolbox needs, for those who may edit; empty for everyone else.
     *
     * @return array<string, mixed>
     */
    public function adminMeta(): array
    {
        return $this->adminMeta ??= $this->services->adminMeta->forContent($this->content());
    }

    public function fields(): ContentFields
    {
        return $this->fields ??= ContentFields::of(fn (): Content => $this->content(), $this->services->fields);
    }
}
