<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/** An image at one variation, as a template needs it. */
final readonly class Image
{
    public function __construct(
        public string $uri,
        public ?string $alt = null,
        public mixed $caption = null,
    ) {
    }

    public function getMediaType(): string
    {
        return 'image';
    }
}
