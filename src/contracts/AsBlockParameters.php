<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/**
 * Declares a class as a provider of parameters for blocks of some content types: #[AsBlockParameters(['grid_cards', 'slider'])].
 * Registered by this attribute alone. Providers of one type run in order of priority, the highest first.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsBlockParameters
{
    /**
     * @param list<string> $types     The content types of the blocks
     * @param list<string> $viewTypes The view types it applies to (all of the theme's by default)
     */
    public function __construct(
        public array $types,
        public array $viewTypes = [],
        public int $priority = 0,
    ) {
    }
}
