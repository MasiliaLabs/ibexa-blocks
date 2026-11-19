# Changelog

All notable changes to `masilia/ibexa-blocks` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

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

[Unreleased]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.4.1...HEAD
[0.4.1]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/MasiliaLabs/ibexa-blocks/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-blocks/releases/tag/0.1.0
