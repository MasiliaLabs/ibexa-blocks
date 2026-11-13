<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/** A place on a map. */
final readonly class Coordinates
{
    public function __construct(
        public ?float $latitude,
        public ?float $longitude,
        public ?string $address,
    ) {
    }
}
