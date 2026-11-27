# Changelog

All notable changes to `masilia/ibexa-blocks` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

### Added
- `favicons` of a theme.yaml can be a list of links (`{rel, href, sizes, type}`) for an icon set the convention does not know (an SVG logo, say), as well as a folder; `favicons: ''` means no icons and still stops the themes below.
- The setting `masilia_ibexa_blocks.system.<siteaccess or group>.favicons` gives a site its own icons over those of its theme (two sites that share a design no longer share their icons).
- `theme:check` checks the icons once the assets are built: an error for a declared file that is missing or spelled with another case (a 404 on a server that tells the two apart), for a folder that is not in `public/`, a notice for a standard icon the folder lacks.

### Changed
- The layout links only the icon files that exist, under their exact name, where it linked all six whatever the folder held. `theme_layout('favicons')` now gives the list of links (`rel`, `href`, `sizes`, `type`) instead of the folder path, so a theme that overrides the `favicons` block and builds the paths itself must loop over that list.
- **Breaking:** the base layout has no `seo_metas` block any more: it is replaced by three blocks of the `<head>`, `favicons`, `fonts` and `seo`, so a theme overrides only the part it needs (its own icons, say). A theme that overrides `seo_metas` must override these instead (`{{ parent() }}` of `favicons` and `fonts` keeps the icons and fonts of theme.yaml).

### Added
- `novactive/ezseobundle` is suggested in composer.json: the `seo` block of the layout includes its head template and the page models have its `novaseometas` field. Both are optional: without the bundle the block is empty and `model:install` skips the field.

## [0.6.4] - 2026-11-24

### Fixed
- The search page (`/search`, route `search_page`) is no longer matched on the admin siteaccesses. It is loaded before Ibexa's routes (it must win on the front), so on an admin siteaccess it hid the admin's own `/search` and answered 500 (`Variable "logo_text" does not exist` in the front header). The route now carries the condition `not siteaccess_in_group('%admin_group_name%')`; `siteaccess_in_group()` is a new function of the routing expression language, provided by the bundle (the groups of a siteaccess are objects, so `in` cannot test them).
- Docs: the skill says to run `cache:pool:clear --all` after a database restore or an SQL change (the persistence cache of Ibexa is in the application pool, `cache:clear` leaves it).

## [0.6.3] - 2026-11-20

### Added
- `masilia:blocks:theme:check` reports an error for each entry of the themes of a design that is not in `public/build/entrypoints.json` (the pages of a site that uses it answer 500 "Could not find the entry ..."); nothing is said while the assets were never built.

### Fixed
- Docs: how the Encore build of a project gets the entries of the themes (the skeleton of Ibexa does not), in the README, the skill, `theme:create` and the recipe, which now copies the helper `assets/masilia-themes.js`.

## [0.6.2] - 2026-11-20

### Fixed
- Docs: a multi-line text field reaches a template as a `Text` value printed with `<br />`; `fields.<name>.rawText` is the text as written.

## [0.6.1] - 2026-11-20

### Added
- Docs: a *Stay informed* section in the README: how to be told of new releases (GitHub Watch, the releases feed, `composer outdated`, Dependabot) and where to read what changes.

## [0.6.0] - 2026-11-20

### Added
- The blocks of a page live in a folder under that page, named after it (`<page name> Blocks`, suffix `conventions.blocks_folder_suffix`), found by the remote id `<page remote id>_blocks`. `site:init` and `site:create` make the one of the home page. The full view of such a folder answers 404 (`BlocksFolderViewProvider`); a `content_view` of the project wins. `Masilia\IbexaBlocks\Content\BlocksFolder` holds the rules for the packages that make or find the folder. Settings `conventions.blocks_folder_suffix` and `conventions.folder_type`.
- `site:init` and `site:create` skip the folder of the home's blocks, with the way out (`model:install --allow-container`, then run again), when `home_page` is not a container yet: the rest of the tree is made as before.
- `masilia:blocks:model:install --allow-container`: makes a type a container when its definition says so and it is not yet (never the other way round), for the projects that installed `home_page` before it became a container.

### Changed
- **Breaking (the socle):** `home_page` is a container (so that its blocks can go under it). Projects that already have the type keep it as it is until `model:install --allow-container`.

## [0.5.4] - 2026-11-20

### Added
- `masilia:blocks:site:create --admin-host=<host>` registers an admin siteaccess `admin_<site>` on that host (in the languages of the site) with the draft site.

### Changed
- `theme:create --standalone` writes stubs for `layout/header.twig` and `layout/footer.twig` and sets `layout: { notifications: false }` in the manifest: the layout of the package asks the theme for them, and a standalone theme has no theme below it (a 500 "Unable to find template" otherwise). The message of `theme:create --extends=<theme>` for a theme without `theme.yaml` says to name an existing theme or use `--standalone`.

### Fixed
- Docs: the view types (`block_item` is the view type of `cards/`, `full` is not in the default `view_types`), where block contents live (`home_page` is not a container), and `--extends` needing an existing theme with a `theme.yaml`.

## [0.5.3] - 2026-11-19

### Added
- Docs: a *With Symfony Flex* section in the README and in the skill: the Masilia recipes (`MasiliaLabs/recipes`) enable the bundle and write its route and config files on `composer require`.

## [0.5.2] - 2026-11-19

### Fixed
- Docs: `model:install` says that the `metas` field is skipped, with a note, when Nova SEO is not installed; the skill says that Flex only enables the bundle (routes and config are written by hand).

## [0.5.1] - 2026-11-19

### Added
- An agent skill, `skills/masilia-ibexa-blocks`, for AI coding agents that work with the package; the README says how to install it.

### Changed
- A block says where it can be placed with `allowed_on: [page, home_page]` in its definition. `model:install` and `model:sync-blocks` add it only to the blocks field of those types (when that field restricts the types), instead of adding every block of the group to `page` and `home_page` as 0.5.0 did. A block with no `allowed_on` is left where it is.

## [0.5.0] - 2026-11-19

### Added
- The content model of a site comes with the package: `masilia:blocks:model:install` creates the groups and the content types `layout_config`, `site`, `home_page` and `page` (the socle), and the types the project defines as `*.yaml` files in `config/content_model/` (and in the directories of `masilia_ibexa_blocks.model.directories`, which a package can add by prepending the setting). It only creates and completes: a type or a field that exists is never changed or removed. Options: `--dry-run`, `--no-socle`, `--only`.
- Every content type of the group of the blocks is allowed in the blocks field of `page` and `home_page` when those fields restrict the types (`model:install`, and `masilia:blocks:model:sync-blocks` alone for an existing project).
- `masilia:blocks:site:init` starts the content tree of a site (container, layout, home page, media folder) with stable remote ids; `masilia:blocks:site:create` does it and then registers the site, as a draft, in `masilia/ibexa-sites` (refused before any content is written when the registry refuses it), or prints the siteaccess configuration to paste. It never activates a site.
- `masilia:blocks:theme:create --standalone`: a theme that builds on none. `theme:create` prints the `design_list` line to add (the list of designs stays yours), and `theme:check` reports a theme that is in no design.
- Settings `conventions.home_type` (default `home_page`) and `conventions.site_type` (default `site`).

## [0.4.1] - 2026-11-19

### Fixed
- A list block that fills itself (grid, slider, preview) sorts by the field of its content type only when that field exists and can be searched; otherwise by the date of publication. A type without a `title` (a partner, for instance) no longer fails the page.

## [0.4.0] - 2026-11-17

### Added
- `items_field_by_type`: the field that holds the child items of the block types that do not call it `items` (for example `info_cards: cards`).

### Changed
- Declares the dependencies the code uses directly (`twig/twig`, Symfony console and HTTP foundation, `psr/container`); examples and test fixtures are neutral.

## [0.3.0] - 2026-11-14

### Added
- Contracts for the packages that build on the blocks (`Themes`, `ShownViews`, `Subscribers`, `ListPreview`).

## [0.2.0] - 2026-11-13

### Added
- List blocks (auto items, featured, all posts) as providers driven by configuration.
- Contact forms from a matrix of questions (form builder, mailer, block provider).
- Newsletter subscription with double opt-in (entity and idempotent migration).
- `ShownBlocks`: the block types a design has a template for.

## [0.1.0] - 2026-11-13

First release.

### Added
- Themes and blocks: one controller, `theme.yaml`, providers instead of conditionals.
- The site around the page: layout, menus, languages, full views.
- Search and forms by mail, driven by configuration.
- Themes as a mechanism: base layout, theme tooling, asset discovery.

[Unreleased]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.6.4...HEAD
[0.6.4]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.6.3...0.6.4
[0.6.3]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.6.2...0.6.3
[0.6.2]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.6.1...0.6.2
[0.6.1]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.5.4...0.6.0
[0.5.4]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.5.3...0.5.4
[0.5.3]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.5.2...0.5.3
[0.5.2]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.5.1...0.5.2
[0.5.1]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.5.0...0.5.1
[0.5.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-blocks/releases/tag/0.1.0
