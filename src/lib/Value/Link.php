<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/**
 * A link a template prints: where it goes (nothing, for a URL field left empty) and what it is called. It answers to the names a menu item has
 * (uri, name, label, extras) so that a template does not care where the link comes from.
 */
final readonly class Link
{
    /**
     * @param array<string, mixed> $extras What the code that made the link knows about its target (for example its content type)
     */
    public function __construct(
        public ?string $uri,
        public string $name,
        public array $extras = [],
    ) {
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getExtra(string $name, mixed $default = null): mixed
    {
        return $this->extras[$name] ?? $default;
    }
}
