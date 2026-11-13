<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/**
 * Marks a class as the renderer of some field types: #[AsFieldRenderer(['ibexa_string', 'ibexa_email'])]. The class is
 * registered by this attribute alone (autoconfiguration): nothing to tag.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsFieldRenderer
{
    /**
     * @param list<string> $types The field type identifiers the renderer handles
     */
    public function __construct(public array $types)
    {
    }
}
