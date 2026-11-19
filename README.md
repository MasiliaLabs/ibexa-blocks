# Ibexa Blocks

[![CI](https://github.com/MasiliaLabs/ibexa-blocks/actions/workflows/ci.yml/badge.svg)](https://github.com/MasiliaLabs/ibexa-blocks/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/masilia/ibexa-blocks.svg)](https://packagist.org/packages/masilia/ibexa-blocks)
[![PHP](https://img.shields.io/packagist/php-v/masilia/ibexa-blocks.svg)](https://packagist.org/packages/masilia/ibexa-blocks)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Front-office rendering of pages made of blocks for Ibexa: a content model for templates, themes that extend each other, and the pieces every theme shares.

`masilia/ibexa-blocks` is part of a family of packages for [Ibexa](https://www.ibexa.co) that work together or alone. See [the other packages](#the-masilia-packages).

## What it is

The **front office** of an Ibexa site whose pages are made of blocks. A page holds an ordered list of block contents; each block is rendered by a Twig template named after its content type. This package gives templates what they need — a flat, ready-to-use content model — and the pieces every theme shares, so a theme is *assets plus templates* and nothing else.

## Features

- **Themes that extend each other**: a theme is a folder of templates (`blocks/<type>.twig`, `cards/<type>.twig`, `full/<type>.twig`…) and a `theme.yaml`. What a theme does not write comes from the next theme of the design (Ibexa's design engine chain).
- **One controller** renders every page and block; behaviour that differs by block type is a *provider*, not an `if`.
- **A content model for templates**: each field of a block becomes a Twig variable, with value objects for images (and their variations), files, links, text and coordinates.
- **The site around the page**: layout configuration, menus, languages switcher and full views.
- **The content model**: the socle of content types (layout, site, home page, page), the content types of the project, and the creation of a site (tree and registration), by console commands.
- **List blocks**: automatic items (the latest of a type), featured items and "all posts", driven by configuration.
- **Search** (`/search` and JSON endpoints) with sorts, facets and popular searches.
- **Forms by mail**: contact forms built from a matrix of questions, mailed to the recipients the block names.
- **Newsletter** with double opt-in (`/api/subscribe`, confirmation and unsubscribe links, `app_subscriber` table).
- **Theme tooling**: `masilia:blocks:themes`, `masilia:blocks:theme:check`, `masilia:blocks:theme:create`.
- **Contracts** (`Themes`, `ShownViews`, `Subscribers`, `ListPreview`…) for the packages that build on it.

## Requirements

- PHP 8.3 or later
- Ibexa 5.0 (Symfony 7.4)

Works better with (optional):

- [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) sets the layout, name and contact of each site from its registry
- [`masilia/editorial-toolbox`](https://github.com/MasiliaLabs/editorial-toolbox) in-context editing: pages and blocks are marked for it
- [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) the assistant builds pages with your blocks

## Installation

```bash
composer require masilia/ibexa-blocks
```

1. Enable the bundle in `config/bundles.php`:

   ```php
   Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle::class => ['all' => true],
   ```

2. Import the routes. Load them before Ibexa's own, which has a `/search` of its own (files of `config/routes/` are read in alphabetical order, so a name that sorts first works, e.g. `app_masilia_ibexa_blocks.yaml`):

   ```yaml
   masilia_ibexa_blocks:
       resource: '@MasiliaIbexaBlocksBundle/Resources/config/routing.yaml'
   masilia_ibexa_blocks_newsletter:        # only if you use the newsletter
       resource: '@MasiliaIbexaBlocksBundle/Resources/config/routing_newsletter.yaml'
   ```

3. If you use the newsletter, create its table (`doctrine/orm` and `doctrine/doctrine-migrations-bundle` are needed for that part):

   ```bash
   php bin/console doctrine:migrations:migrate
   ```

## Configuration

```yaml
masilia_ibexa_blocks:
    conventions:
        page_type: page            # the content type of a page
        blocks_field: blocks       # the field of a page that holds its blocks
        items_field: items         # the field of a block that holds its child items
        items_field_by_type:       # block types that call it differently
            info_cards: cards
    system:
        my_site_group:
            layout_remote_id: my_layout_config   # the layout of the site (logo, footer)
    blocks:
        flatten_fields: true       # each field is a Twig variable
        lists:
            auto_items: { types: [grid_cards, slider] }
            featured:   { types: [grid_cards] }
            all_posts:  { types: [all_posts] }
    search:
        allowed_content_types: [article, page]
        default_content_types: [article]
    forms:
        content_types: [steps_form]
        from_address: contact@example.org
    newsletter:
        enabled: true
        from_address: contact@example.org
```

Every key has a default; the full tree is `bin/console config:dump-reference masilia_ibexa_blocks`. If you use [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites), the layout of each site is read from its registry instead of `system:`.

### A theme

```
templates/themes/my_site/
    theme.yaml
    blocks/hero_banner.twig
    cards/card_item.twig
    full/page.twig
```

```yaml
# theme.yaml
assets: { directory: assets/themes/my_site, entry: my_site, styles: [fa-icons] }
favicons: build/images/my_site/icons
fonts: ['https://fonts.googleapis.com/css2?family=Inter&display=swap']
```

Templates are found by the name of their content type; `theme.yaml` only needs `views:` for an exception. `php bin/console masilia:blocks:theme:check <design>` tells you which block type has no template, and `masilia:blocks:themes --json` gives your Encore build the entries to compile.

## Content model, themes and sites

**Content types are global**: Ibexa has one set of types for the whole repository, not one per siteaccess. So the types (blocks, block items, your own content such as articles or events) are defined **once, at the level of the project**, and shared by all its sites; a **theme only brings templates and assets**. A library of blocks and a standard design are meant to come as a separate package; this package is the engine.

### 1. The content model

The package ships the **socle**:

| Content type | Group | What it is |
| --- | --- | --- |
| `layout_config` | Configurations | The layout of a site: logo, menus, social links, newsletter, footer |
| `site` | Configurations | The container of a site: title, domain, description, analytics, maintenance |
| `home_page` | Content | The home page: a title and its `blocks` |
| `page` | Content | A page: a title, a description, its `blocks` and SEO fields |

Your blocks and content go in `config/content_model/*.yaml`, one file per type:

```yaml
identifier: hero_banner
group: Blocks          # the group of the blocks (conventions.block_group): a block
allowed_on: [home_page]   # where it can be placed: the types whose blocks field accepts it (page, home_page, both...)
names: { eng-GB: Hero banner }
name_pattern: '<title>'
fields:
  - { identifier: title, type: ibexa_string, name: Title, required: true, position: 10 }
  - { identifier: image, type: ibexa_image, name: Image, translatable: false, position: 30 }
```

Fields take `type` (an Ibexa field type identifier), `name`, `description`, `required`, `searchable`, `translatable`, `category`, `position`, `settings` and `validator`. A type of another group (`Content`...) is content of your own, shown by `full/<type>.twig` and `line/<type>.twig`. More directories can be listed in `masilia_ibexa_blocks.model.directories` (a package adds its own by prepending that setting).

```bash
php bin/console masilia:blocks:model:install --dry-run   # what would be created
php bin/console masilia:blocks:model:install             # create it
php bin/console masilia:blocks:model:sync-blocks         # an existing project: place the blocks where they say they go
```

The command is safe to run again: it creates what is missing and adds the missing fields; it never changes or removes a type or a field that exists, and says so when a field differs from the definition. Field types that are not installed (SEO fields without Nova SEO, for instance) are skipped with a note. Some blocks are meant for the home page, others for the pages, others for both: a block says where it goes with `allowed_on`, and when the `blocks` field of that type restricts the types it accepts, the block is added to it. A block that says nothing is left where it is, and a type that does not restrict its blocks field accepts them all. Nothing is ever taken off a list. The names follow the [conventions](#configuration) (`page_type`, `home_type`, `blocks_field`, `block_group`...).

### 2. A theme per site

A theme is assets plus templates, **standalone** or **inherited** through the design engine of Ibexa:

```bash
php bin/console masilia:blocks:theme:create acme --extends=standard   # only what differs from standard
php bin/console masilia:blocks:theme:create solo --standalone         # all its templates and styles
```

Declare it in a design yourself (the list of designs stays yours), for instance `ibexa_design_engine.design_list.acme: [acme, standard]`, write the templates named after the content types (`blocks/<type>.twig`, `cards/`, `line/`, `full/`), and check it:

```bash
php bin/console masilia:blocks:theme:check acme     # missing templates, assets, dead files, a theme in no design
```

### 3. A site

```bash
php bin/console masilia:blocks:site:create "Acme" --domain=acme.example.org --design=acme --language=eng-GB
```

It creates the content tree (container, layout, home page, media folder: `site:init` does that part alone) and, when [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) is installed, registers the site as a **draft** (root = the home page, the layout, the design, a siteaccess per language). The registry is asked first: a site it refuses (unknown design, a host already taken...) writes no content. Without `ibexa-sites` it prints the siteaccess configuration to adapt. It never activates a site, since that serves its hosts: do it from the admin or with `masilia:sites:set-status <id> active`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## The Masilia packages

| Package | What it does |
| --- | --- |
| [`masilia/ibexa-admin-kit`](https://github.com/MasiliaLabs/ibexa-admin-kit) | React components in the design of the Ibexa admin, for admin pages |
| [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) | A registry of sites that Ibexa serves without a deploy |
| [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) | The AI assistant for Ibexa: capabilities, providers, chat, approval flow |
| [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) | The assistant builds sites and pages out of blocks |
| [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) | AI suggestions and image generation in the content editing form |
| [`masilia/ibexa-blocks`](https://github.com/MasiliaLabs/ibexa-blocks) | Front-office rendering of pages made of blocks, with themes |
| [`masilia/editorial-toolbox`](https://github.com/MasiliaLabs/editorial-toolbox) | In-context editing toolbar for the front office |

**What requires what** (Composer installs it for you):

- `ibexa-sites` requires `ibexa-admin-kit`
- `ibexa-ai` requires `ibexa-admin-kit`
- `ibexa-ai-editor` requires `ibexa-ai`
- `ibexa-ai-craft` requires `ibexa-ai`, `ibexa-blocks` and `ibexa-sites`
- `ibexa-blocks` and `editorial-toolbox` require none of the others; they are happy to find `ibexa-sites` (and each other) when installed.

## Compatibility

| Package version | PHP | Ibexa |
| --- | --- | --- |
| `latest` | 8.3+ | 5.0 |

## Download

- **Composer** (recommended): `composer require masilia/ibexa-blocks` — [ibexa-blocks on Packagist](https://packagist.org/packages/masilia/ibexa-blocks)
- **Archive** of a release: [GitHub releases](https://github.com/MasiliaLabs/ibexa-blocks/releases) (each release lists what changed)
- **Source**: `git clone https://github.com/MasiliaLabs/ibexa-blocks.git`
- What changed between versions: [CHANGELOG.md](CHANGELOG.md)

## Support, contributing and security

- Questions and bug reports: [GitHub issues](https://github.com/MasiliaLabs/ibexa-blocks/issues)
- Pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md)
- Security problems: please follow [SECURITY.md](SECURITY.md) and do not open a public issue

## License

Released under the [MIT license](LICENSE). Copyright © 2026 MasiliaLabs.

Ibexa itself is distributed under its own licenses (Ibexa OSS: GPL-2.0), which apply to the project that uses this package. This package is an independent project, not affiliated with or endorsed by Ibexa AS; "Ibexa" is a trademark of its owner.
