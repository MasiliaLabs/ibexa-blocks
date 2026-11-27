# Ibexa Blocks

[![CI](https://github.com/MasiliaLabs/ibexa-blocks/actions/workflows/ci.yml/badge.svg)](https://github.com/MasiliaLabs/ibexa-blocks/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/masilia/ibexa-blocks.svg)](https://packagist.org/packages/masilia/ibexa-blocks)
[![Downloads](https://img.shields.io/packagist/dt/masilia/ibexa-blocks.svg)](https://packagist.org/packages/masilia/ibexa-blocks/stats)
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

### With Symfony Flex

The Masilia recipes do the first steps for you. Add their endpoint once to the `composer.json` of your project, after the one of Ibexa:

```json
"extra": {
    "symfony": {
        "endpoint": [
            "https://api.github.com/repos/ibexa/recipes/contents/index.json?ref=flex/main",
            "https://api.github.com/repos/MasiliaLabs/recipes/contents/index.json?ref=flex/main",
            "flex://defaults"
        ]
    }
}
```

Then `composer require masilia/ibexa-blocks` enables the bundle and writes `config/routes/aa_masilia_ibexa_blocks.yaml` (loaded before Ibexa's routes) and `config/packages/masilia_ibexa_blocks.yaml`. What is left is yours: the content model, a theme and a site (`masilia:blocks:*`), and the newsletter routes if you use it. The manual steps above and below stay valid.

## Configuration

Every setting is **per site**: it goes under `system`, in `default` for what all the sites share, then in a siteaccess group (a site made with `masilia/ibexa-sites` is the group `<identifier>_group`) or a siteaccess for what differs. Nothing is left at the root of the configuration.

```yaml
masilia_ibexa_blocks:
    system:
        default:                   # all the sites
            conventions:
                page_type: page            # the content type of a page
                blocks_field: blocks       # the field of a page that holds its blocks
                items_field: items         # the field of a block that holds its child items
                items_field_by_type:       # block types that call it differently
                    info_cards: cards
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
                subject: Confirm your subscription
        my_site_group:             # what differs for one site
            layout_remote_id: my_layout_config   # the layout of the site (logo, footer)
            newsletter:
                subject: Confirm your subscription to My Site
                list_name: the My Site newsletter
```

The sections are `conventions`, `model`, `themes`, `blocks`, `search`, `fields`, `forms`, `newsletter` and `rate_limit`, with `layout_remote_id`, `layout_menus` and `favicons`; the full tree is `bin/console config:dump-reference masilia_ibexa_blocks` (the defaults are those of the code: a setting nobody says is not in the tree).

#### How a site gets its settings

Each setting is read **when it is used**, for the site of the request, in this order: the **registry of the sites** (`masilia/ibexa-sites`: the `site_email` of the site is the address the mails are sent from, `layout_remote_id` the layout), then the siteaccess, then its group, then `default`, then the default of the code. A setting is looked up on its own: a site that says `newsletter.subject` keeps the `from_address` of `default`. A list (`content_types: [...]`) or a table (`sorts`, `facets`, `variations`, `items_field_by_type`, `auto_item_sort_fields`) is one setting: the more specific scope **replaces** it, it is not merged.

- The newsletter, the list blocks and the contact forms are always there once one scope turns them on; a site that did not answers `404` on the newsletter routes, and the list providers leave its blocks alone.
- `themes.directories` and `model.directories` are those of the site; a command (`theme:check`, `model:install`) uses the default siteaccess, or `--siteaccess`. A package that adds directories writes them under `system.default`.
- Ibexa does not check the name of a scope: a typo is a setting no siteaccess reads (`bin/console debug:config masilia_ibexa_blocks` shows what was read).

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
fonts: ['https://fonts.googleapis.com/css2?family=Inter&display=swap']
```

`fonts` lists stylesheets of web fonts, each an https URL or the path in `public/` of a stylesheet the project hosts itself (`build/fonts/fonts.css`); `preconnect` lists the origins the browser connects to early, explicitly (`- https://fonts.googleapis.com`, `- { origin: https://fonts.gstatic.com, crossorigin: true }`; none for self-hosted fonts); the layout links them in its `preconnect` block, and like the other keys they come from the first theme of the design that has them, so a theme that changes `fonts:` says its `preconnect:` too. The **icons belong to the site**, not to the theme: the setting `masilia_ibexa_blocks.system.<siteaccess or group>.favicons` is a folder of `public/` (`android-chrome-192x192.png`, `favicon-32x32.png`, `favicon-16x16.png`, `site.webmanifest`, `favicon.ico`): the layout links the files that are there, under the exact name they have (case matters on a server). `''` means no icons. For a set the convention does not know, give the links: `favicons: [{ rel: icon, type: image/svg+xml, href: build/images/my_site/logo.svg }]`. That is how two sites on one design differ: with `masilia/ibexa-sites` each site is a group named by its identifier (`masilia_ibexa_blocks: { system: { regain_group: { favicons: build/images/regain/icons } } }`). A `favicons` key in a `theme.yaml` is refused. The layout shows them in its `favicons` block; leave the icons of the SEO bundle (`nova_ezseo.default_links`, `Shortcut icon`) empty so that they are not linked twice. `theme:check` reports an icon file of the site that is missing or spelled with another case.

The `layout.twig` of a theme is two lines, `{% extends "@MasiliaIbexaBlocks/layout.twig" %}`, plus the blocks it overrides: `charset`, `favicons`, `preconnect`, `fonts`, `seo_metas` (the SEO metas), `head_extra` (anything else in the `<head>`: a stylesheet, a script), `stylesheets`, `toolbox`, `header`, `content`, `footer`, `modals` and `javascripts`. Use `{{ parent() }}` to add to a block instead of replacing it. What the layout needs from a theme (assets, favicons, fonts, notifications) is read from the `theme.yaml` files of the design, the first theme of the chain that says each thing winning: a theme that says nothing about `favicons` shows the icons of the theme below it, so `favicons: ''` is how a theme asks for none.

Templates are found by the name of their content type; `theme.yaml` only needs `views:` for an exception. `php bin/console masilia:blocks:theme:check <design>` tells you which block type has no template, and `masilia:blocks:themes --json` gives your Encore build the entries to compile.

The public search is bounded by the `search` node: `api.max_items` (page size), `api.max_term_length` and `api.max_tags` (what a request may send to the index) and, when you want `/api/posts/suggestions` kept by the browser and the HTTP cache, `api.suggestions_cache_ttl` in seconds.

The endpoints that send a mail can be limited per address, which is off unless you ask (it needs `symfony/rate-limiter`):

```yaml
masilia_ibexa_blocks:
    system:
        default:
            rate_limit:
                forms_submit: { limit: 5, interval: '10 minutes' }   # POST /api/forms/submit
                subscribe:    { limit: 3, interval: '1 hour' }       # POST /api/subscribe
```

Over the limit the answer is `429` with a `Retry-After` header. A visitor is told apart by its address (`Request::getClientIp()`): behind a proxy such as Varnish, set `framework.trusted_proxies` (and `trusted_headers`), or every visitor has the address of the proxy and the limit becomes one for the whole site. The unsubscribe link of the newsletter shows a confirmation page first (a theme may write `emails/unsubscribe_confirm.html.twig`; its form posts to the current address); the change is made by the button, so a mail scanner that opens the link unsubscribes nobody.

## Showing pages and blocks

A template is found by the **view type** and the name of the content type, in the themes of the design:

| View type | Template | What it shows |
| --- | --- | --- |
| `blocks` | `blocks/<type>.twig` | a block of a page |
| `block_item` | `cards/<type>.twig` | an item of a block (a card, a slide...) |
| `line` | `line/<type>.twig` | a line of a list |
| `embed` | `embed/<type>.twig`, then `line/<type>.twig` | a content embedded in another |
| `full` | `full/<type>.twig` | a page of its own (a page, an article, an event...) |

The view type is **`block_item`**, not `cards`: `cards` is only the folder. In a block template, show its items with `{{ render_blocks(fields.items, {}, 'block_item') }}` (a view type that does not exist shows nothing, without an error).

**`full` is not shown by default**: `masilia_ibexa_blocks.system.default.blocks.view_types` is `[blocks, block_item, line]`, so a page falls back to the default content view of Ibexa (a dump of its fields) unless your project routes it. Either add the view types you use:

```yaml
masilia_ibexa_blocks:
    system:
        default:
            blocks:
                view_types: [blocks, block_item, line, embed, full]
```

so that `full/<type>.twig` of your theme shows pages (a type that holds blocks and has no template of its own is shown by the page of the package, `@MasiliaIbexaBlocks/full/blocks_page.twig`), or write your own `content_view` rule, which always wins.

**Multi-line text in a template**: a field of type `ibexa_text` (several lines) reaches the template as a `Text` value that prints with its line breaks turned into `<br />`. To split it into lines, show it in a `<pre>`, or print it your way, use the text as it was written: `fields.<name>.rawText` (`{{ fields.notes.rawText|nl2br }}` shows the breaks and escapes the rest; `{% for line in fields.notes.rawText|split("\n") %}` goes through the lines).

**Where the block contents live**: a block is a content of its own, related from the blocks field of a page (relations follow the id of the content, not its place). By convention the blocks of a page are stored in **a folder that is a child of that page**, named after it: `<page name> Blocks` ("Home Blocks", "About Blocks"; the suffix is `conventions.blocks_folder_suffix`). The tree of a site:

```
site
├── layout_config
├── media folder
└── home_page
    ├── Home Blocks          the blocks of the home
    ├── About (page)
    │   └── About Blocks     the blocks of the page
    └── ...
```

`home_page` is a container for this. The socle says so; a project that installed it before has to allow the change once: `masilia:blocks:model:install --allow-container` (a type is never changed otherwise). `site:init` and `site:create` make the home folder; `masilia/ibexa-ai-craft` and `masilia/editorial-toolbox` make a page's folder when they create its first block, and find it by its **remote id** (the page's remote id and `_blocks`), so renaming a page changes nothing. The folders of blocks are storage, not pages: their full view answers **404** (a `content_view` rule of the project still wins). Blocks that exist elsewhere are not moved.

**A standalone theme** (`theme:create --standalone`) has no theme below it: it writes stubs for `layout/header.twig` and `layout/footer.twig` and sets `layout: { notifications: false }` in its `theme.yaml` (remove that line once you add `layout/parts/notification.html.twig`). `--extends=<theme>` needs an existing theme **with a `theme.yaml`**: `standard` is only one when your project (or a package that brings the standard design) has it, so on a fresh project start with `--standalone`.

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

Fields take `type` (an Ibexa field type identifier), `name`, `description`, `required`, `searchable`, `translatable`, `category`, `position`, `settings` and `validator`. A type of another group (`Content`...) is content of your own, shown by `full/<type>.twig` and `line/<type>.twig`. More directories can be listed in `masilia_ibexa_blocks.system.default.model.directories` (a package adds its own by prepending that setting).

```bash
php bin/console masilia:blocks:model:install --dry-run   # what would be created
php bin/console masilia:blocks:model:install             # create it
php bin/console masilia:blocks:model:sync-blocks         # an existing project: place the blocks where they say they go
```

The command is safe to run again: it creates what is missing and adds the missing fields; it never changes or removes a type or a field that exists, and says so when a field differs from the definition. Field types that are not installed are skipped with a note: the `metas` field of `page` and `home_page` is a Nova SEO field (`novaseometas`), so without that bundle you read `field "metas" skipped: the field type "novaseometas" is not installed`, which is expected. Some blocks are meant for the home page, others for the pages, others for both: a block says where it goes with `allowed_on`, and when the `blocks` field of that type restricts the types it accepts, the block is added to it. A block that says nothing is left where it is, and a type that does not restrict its blocks field accepts them all. Nothing is ever taken off a list. The names follow the [conventions](#configuration) (`page_type`, `home_type`, `blocks_field`, `block_group`...).

### 2. A theme per site

A theme is assets plus templates, **standalone** or **inherited** through the design engine of Ibexa:

```bash
php bin/console masilia:blocks:theme:create acme --extends=standard   # only what differs from standard (an existing theme with a theme.yaml)
php bin/console masilia:blocks:theme:create solo --standalone         # all its templates and styles
```

**Build the assets of the themes.** A theme says in its `theme.yaml` which entry it builds and from which sources; the Encore build of your project has to add them, and the skeleton of Ibexa does not: without it a site that uses the theme answers **500** (`Could not find the entry "acme.css" in entrypoints.json`). The recipe copies a helper to `assets/masilia-themes.js`: add one line to `webpack.config.js`, before `Encore.getWebpackConfig()`:

```js
require('./assets/masilia-themes.js')(Encore, __dirname);
```

Without the recipe, the helper is this: read `php bin/console masilia:blocks:themes --json` (`{"entries": {"acme.css": "assets/themes/acme/scss/main.scss", ...}, "problems": []}`) and `Encore.addEntry(entry, [path.resolve(__dirname, source)])` for each, then `yarn encore dev`. `masilia:blocks:theme:check` tells you when an entry is missing from `public/build/entrypoints.json`.

Declare it in a design yourself (the list of designs stays yours), for instance `ibexa_design_engine.design_list.acme: [acme, standard]`, write the templates named after the content types (`blocks/<type>.twig`, `cards/`, `line/`, `full/`), and check it:

```bash
php bin/console masilia:blocks:theme:check acme     # missing templates, assets, dead files, a theme in no design
```

### 3. A site

```bash
php bin/console masilia:blocks:site:create "Acme" --domain=acme.example.org --design=acme --language=eng-GB [--admin-host=admin.acme.example.org]
```

It creates the content tree (container, layout, home page, media folder: `site:init` does that part alone) and, when [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) is installed, registers the site as a **draft** (root = the home page, the layout, the design, a siteaccess per language, and with `--admin-host` an admin siteaccess `admin_<site>` on that host). The registry is asked first: a site it refuses (unknown design, a host already taken...) writes no content. Without `ibexa-sites` it prints the siteaccess configuration to adapt. It never activates a site, since that serves its hosts: do it from the admin or with `masilia:sites:set-status <id> active`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## AI agent skill

This package ships a skill for AI coding agents (Claude Code and other agents that read `SKILL.md` skills): how to install, configure, operate and troubleshoot `masilia/ibexa-blocks`. It lives in [`skills/masilia-ibexa-blocks`](skills/masilia-ibexa-blocks/SKILL.md) and comes with the package.

```bash
# For the project (committed with it): from the installed package
mkdir -p .claude/skills && cp -r vendor/masilia/ibexa-blocks/skills/masilia-ibexa-blocks .claude/skills/

# For all your projects: global
mkdir -p ~/.claude/skills && cp -r vendor/masilia/ibexa-blocks/skills/masilia-ibexa-blocks ~/.claude/skills/
```

Restart the agent (or reload its skills) to pick it up. Update the copy after `composer update`.

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

## Stay informed

New versions are announced as **GitHub releases**, with their notes taken from the [CHANGELOG](CHANGELOG.md):

- **On GitHub**: *Watch* this repository, *Custom*, *Releases*, to be notified of each release; or follow the feed `https://github.com/MasiliaLabs/ibexa-blocks/releases.atom` in your reader.
- **In your project**: `composer outdated "masilia/*"` lists what is behind. To be told by a pull request, let Dependabot (or Renovate) watch the Masilia packages:

  ```yaml
  # .github/dependabot.yml
  version: 2
  updates:
    - package-ecosystem: composer
      directory: /
      schedule: { interval: weekly }
      allow:
        - dependency-name: "masilia/*"
  ```

- **Before updating**: the CHANGELOG says what changes; a change that breaks something is marked **Breaking** and explained in [UPGRADE.md](UPGRADE.md). Before 1.0, a minor version (`0.x`) may contain breaking changes: read the notes of the versions you skip. Security problems are announced as GitHub security advisories (see [SECURITY.md](SECURITY.md)).

## Support, contributing and security

- Questions and bug reports: [GitHub issues](https://github.com/MasiliaLabs/ibexa-blocks/issues)
- Pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md)
- Security problems: please follow [SECURITY.md](SECURITY.md) and do not open a public issue

## License

Released under the [MIT license](LICENSE). Copyright © 2026 MasiliaLabs.

Ibexa itself is distributed under its own licenses (Ibexa OSS: GPL-2.0), which apply to the project that uses this package. This package is an independent project, not affiliated with or endorsed by Ibexa AS; "Ibexa" is a trademark of its owner.
