<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/**
 * An image field as a template gets it: the image at the variation the template asks for, `image.at('card')`. Without asking,
 * `image.uri`, `image.alt` and `image.caption` are those of the original, so a template that needs no variation reads it
 * as it would any image. A variation is made once, and only if it is asked for.
 */
final class ImageSet implements \JsonSerializable
{
    public const ORIGINAL = 'original';

    /** @var array<string, Image|null> */
    private array $variations = [];

    /**
     * @param \Closure(string): (Image|null) $resolve The image at a variation
     */
    public function __construct(private readonly \Closure $resolve)
    {
    }

    /** The image at a variation of the project (ibexa_image_variations); the original when none is named. */
    public function at(string $variation = self::ORIGINAL): ?Image
    {
        if (!array_key_exists($variation, $this->variations)) {
            $this->variations[$variation] = ($this->resolve)($variation);
        }

        return $this->variations[$variation];
    }

    public function getUri(): string
    {
        return $this->at()?->uri ?? '';
    }

    public function getAlt(): ?string
    {
        return $this->at()?->alt;
    }

    public function getCaption(): mixed
    {
        return $this->at()?->caption;
    }

    public function getMediaType(): string
    {
        return 'image';
    }

    public function jsonSerialize(): ?Image
    {
        return $this->at();
    }
}
