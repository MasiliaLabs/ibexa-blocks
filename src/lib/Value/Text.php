<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/**
 * A plain text field: printed with its line breaks (`<br />`), and still available as it was written. It prints as the text
 * with its breaks and nothing more: Twig escapes it when it is printed, so the breaks show as text; use |nl2br on `rawText`,
 * which escapes it and keeps the breaks, to show them.
 */
final readonly class Text implements \Stringable
{
    public function __construct(public string $rawText)
    {
    }

    public function __toString(): string
    {
        return nl2br($this->rawText);
    }
}
