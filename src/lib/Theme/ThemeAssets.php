<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/**
 * The front-end assets of a theme: where its sources are and the names of the entries that are built from them, which its
 * layout includes. The entry `<entry>.css` is built from `<directory>/scss/main.scss` and `<entry>.js` from `<directory>/js/main.js`.
 */
final readonly class ThemeAssets
{
    /**
     * @param string       $directory Relative to the project ("assets/themes/mattcch")
     * @param string       $entry     The name the entries are built under ("mattcch": mattcch.css and mattcch.js)
     * @param list<string> $styles    Entries every page of the theme includes before its own (an icon font, say)
     * @param list<string> $extra     Entries included after its own, as stylesheet and as script (the editing toolbox)
     */
    public function __construct(
        public string $directory,
        public string $entry,
        public array $styles = [],
        public array $extra = [],
    ) {
    }

    public function stylesheetSource(): string
    {
        return $this->directory . '/scss/main.scss';
    }

    public function scriptSource(): string
    {
        return $this->directory . '/js/main.js';
    }
}
