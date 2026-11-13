<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Value;

/** A file to download. */
final readonly class File
{
    public function __construct(
        public string $fileName,
        public ?int $fileSize,
        public ?string $mimeType,
        public string $uri,
    ) {
    }
}
