---
name: masilia-ibexa-blocks
description: Work with masilia/ibexa-blocks, the front-office engine of Ibexa 5 sites made of blocks - content model (page, home_page, layout_config, site, block types), themes (assets + Twig templates named after content types, standalone or inherited through the design engine), block lists, forms, search, newsletter, site creation. Use whenever the user builds or changes a theme, adds or edits a block type or its template, sets up page/home blocks, creates a site (tree, layout, home, design), uses `masilia:blocks:*` commands or Kaliop migrations for blocks, or mentions masilia/ibexa-blocks, blocks_field, items_field, layout_remote_id, even if the package is not named.
---

# masilia/ibexa-blocks

A page holds an ordered list of block contents; each block is rendered by a Twig template named after its content type. A theme is **assets plus templates**, nothing else. Content types are **global to the repository** (not per siteaccess): all sites share the same block types; a theme only decides how they look.

## Install (blank project)
`composer require masilia/ibexa-blocks` (+ `masilia/ibexa-sites` recommended); enable `MasiliaIbexaBlocksBundle`; import `@MasiliaIbexaBlocksBundle/Resources/config/routing.yaml` **before** Ibexa's routes (it has its own `/search`); newsletter: `routing_newsletter.yaml` and `doctrine:migrations:migrate`. Flex only enables the bundle: routes and config are written by hand (name the routes file so that it sorts first, e.g. `aa_masilia_blocks.yaml`). `model:install` prints `field "metas" skipped: ... novaseometas` when Nova SEO is absent: expected.

With the Masilia recipes endpoint in the `composer.json` (`https://api.github.com/repos/MasiliaLabs/recipes/contents/index.json?ref=flex/main` in `extra.symfony.endpoint`, after Ibexa's), `composer require` already enables the bundle and writes the route and config files (see the README, section *With Symfony Flex*); then do what is left. Without the endpoint, do everything by hand.

## Configuration is per site: everything goes under `masilia_ibexa_blocks.system`
`system.default` is what all the sites share; `system.<group>` (`<identifier>_group` for a site of `masilia/ibexa-sites`) or `system.<siteaccess>` is what differs, setting by setting (a list or table is replaced, not merged). Nothing at the root is accepted. The registry of the sites comes first when it has the value (`site_email` is the sender of the mails, `layout_remote_id`). The icons (`favicons`: a folder of public/ or a list of links) are the site's, never the theme's. `masilia:blocks:settings --siteaccess=<name>` shows each value and its origin. Sections: `conventions`, `model`, `themes`, `blocks`, `search`, `fields`, `forms`, `newsletter`, `rate_limit`, plus `layout_remote_id`, `layout_menus`, `favicons`. In PHP, ask `SettingsProvider` when you need a setting: never keep it in a service.

## Conventions (`system.default.conventions`)
`page_type` (page), `home_type` (home_page), `site_type` (site), `blocks_field` (blocks), `block_group` (Blocks), `items_field` (items), `items_field_by_type` (e.g. `info_cards: cards`). Wrong names are the first cause of "blocks do not show". The layout of a site comes from `masilia/ibexa-sites` (`Site::layoutRemoteId`), else `masilia_ibexa_blocks.system.<group>.layout_remote_id`.

## Views and templates
View types and folders: `blocks` -> `blocks/<type>.twig`, `block_item` -> `cards/<type>.twig` (the view type is `block_item`; `cards` is only the folder: `render_blocks(fields.items, {}, 'block_item')`; an unknown view type shows nothing, silently), `line` -> `line/`, `embed` -> `embed/` then `line/`, `full` -> `full/<type>.twig`. **`full` is not in the default `masilia_ibexa_blocks.system.default.blocks.view_types`** (`[blocks, block_item, line]`): a page then shows Ibexa's default field dump. Add `full` (and `embed`) to `view_types`, or write a `content_view` rule. Blocks are contents of their own, related from the blocks field. Convention: the blocks of a page live in a folder **under that page**, named `<page name> Blocks` (`conventions.blocks_folder_suffix`), found by remote id `<page remote id>_blocks`; its full view answers 404; `home_page` must be a container (`model:install --allow-container` once on an existing project; `site:init`/`site:create` make the home's folder). Existing blocks are not moved.

A multi-line `ibexa_text` field reaches a template as a `Text` value that prints with `<br />` for its line breaks: for the text as written (splitting lines, `<pre>`) use `fields.<name>.rawText`.

## 1. The content model (two ways, both valid)
- **Kaliop migrations** (what the IMAL host does): define block types, `page`/`home_page` and their `selectionContentTypes` in migrations. Then no `model:install` is needed; keep the convention names above. The restricted lists of `page.blocks`/`home_page.blocks` are yours to manage there.
- **`masilia:blocks:model:install [--dry-run] [--no-socle] [--only=type]`**: creates the socle (`layout_config`, `site`, `home_page`, `page`) and the types in `config/content_model/*.yaml` (identifier, group, names, name_pattern, fields with Ibexa 5 field types such as `ibexa_string`, `allowed_on: [page, home_page]`). It only creates and completes; it never changes or removes an existing type or field. **Always `--dry-run` first**: on a project that already has these types it would add the socle fields they lack. Group `Blocks` = a block; other groups = content (article, event...) shown by `full/<type>.twig`.
- `allowed_on` places a block on the blocks field of those types, only when that field restricts types; a block without it is left where it is. `masilia:blocks:model:sync-blocks` re-applies this on an existing project.

## 2. A theme per site
`masilia:blocks:theme:create <name> [--extends=<theme> | --standalone]` (`--extends` needs a theme that has a `theme.yaml`: on a fresh project `standard` often does not exist, start with `--standalone`, which also writes `layout/header.twig`, `layout/footer.twig` stubs and `layout: {notifications: false}`) writes `templates/themes/<name>/theme.yaml`, `layout.twig`, and `assets/themes/<name>/scss|js`. Templates are found by name: `blocks/<type>.twig`, `cards/`, `line/`, `full/`; a theme writes only what differs from the next theme of its design. The **design list stays yours** (YAML): `ibexa_design_engine.design_list.<name>: [<name>, standard]` (or `[<name>]`). `masilia:blocks:theme:check <design>` reports blocks without template, asset problems, dead files, a theme in no design. The theme's `layout.twig` is two lines extending `@MasiliaIbexaBlocks/layout.twig` and overriding only the blocks that differ (`favicons`, `preconnect`, `fonts`, `seo_metas`, `head_extra`, `stylesheets`, `toolbox`, `header`, `content`, `footer`, `modals`, `javascripts`); assets, `fonts` (https URLs or paths of public/), `preconnect` (the origins to connect to early, explicit) and `layout.notifications` come from `theme.yaml`, the first theme of the chain that says each thing winning; a site can override the icons with `masilia_ibexa_blocks.system.<group>.favicons`. `masilia:blocks:themes --json` gives Encore the entries; then `yarn encore dev`.

**The build of the themes is not wired by Ibexa's skeleton**: add `require('./assets/masilia-themes.js')(Encore, __dirname);` to `webpack.config.js` (the recipe copies the helper; it reads `masilia:blocks:themes --json` and adds each entry) then `yarn encore dev`. Without it every page of a site that uses the theme answers 500 `Could not find the entry "<theme>.css" in entrypoints.json`; `theme:check` reports it.

## 3. Create the site
`masilia:blocks:site:create "Name" --domain=… --design=<design> --language=eng-GB [--host --admin-host --parent --media-parent --dry-run]`: creates the tree (site container, layout, home page, media folder, stable remote ids, rerunnable) and, with `ibexa-sites`, registers the site as a **draft** (root = home page, layout, design, one siteaccess per language); the registry is asked first, so a refused site writes no content. Without ibexa-sites it prints the siteaccess YAML. `masilia:blocks:site:init` does the tree only. **Never activates**: the user does (`/sites` or `masilia:sites:set-status <id> active`), because that serves the hosts.

## After changes
`cache:clear` and `cache:pool:clear --all`; PHP-FPM reload if PHP files changed; rebuild assets for theme changes. Templates read optional values safely (a missing field must not 500 the page).

## Pitfalls
- New block type without a template in the design chain: the block silently shows nothing; run `theme:check`.
- Block types whose child field is not `items` need `items_field_by_type`.
- A standalone theme needs every template (and its header/footer); an inherited one inherits.
- Do not generate `design_list` or edit the user's YAML silently; print the line to add.
Release and maintenance of the package follow the `masilia-package-release` skill.
