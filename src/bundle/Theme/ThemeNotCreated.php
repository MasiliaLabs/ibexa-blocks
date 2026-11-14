<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

/** A theme that could not be created, and why in a sentence. */
final class ThemeNotCreated extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $invalidName = false)
    {
        parent::__construct($message);
    }
}
