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

    /**
     * An image that is already at its variation stays there: a template written for `image.at('card')` also works on an image
     * a search or a manifest gave at the variation it chose.
     */
    public function at(string $variation = 'original'): self
    {
        return $this;
    }

    public function getMediaType(): string
    {
        return 'image';
    }
}
