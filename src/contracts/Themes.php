<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** The themes of the front office, for the packages that help build them: what exists, whether it is sound, and a new one. */
interface Themes
{
    /**
     * @return array<string, list<string>> Each design and the themes it is made of, the design's own first
     */
    public function designs(): array;

    /**
     * What is wrong with a design: a block type no theme shows, a template or an asset source that is missing, dead files.
     *
     * @return list<array{level: string, theme: ?string, message: string}> level is "error" or "notice"
     *
     * @throws \InvalidArgumentException When there is no such design
     */
    public function check(string $design): array;

    /**
     * Creates a theme on another one: its manifest, a layout on the base layout and the sources of its assets. It writes files
     * of the project.
     *
     * @return array{files: list<string>, next: list<string>} The files written, and what is left to do by hand
     *
     * @throws \InvalidArgumentException When the name is not valid, the theme exists, or the one to extend does not
     */
    public function create(string $name, string $extends = 'standard'): array;
}
