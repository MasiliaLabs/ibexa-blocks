<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/** A plain text field: printed with its line breaks, and still available as it was written. */
final readonly class Text implements \Stringable
{
    public function __construct(public string $rawText)
    {
    }

    public function __toString(): string
    {
        return nl2br(htmlspecialchars($this->rawText, ENT_QUOTES | ENT_SUBSTITUTE));
    }
}
