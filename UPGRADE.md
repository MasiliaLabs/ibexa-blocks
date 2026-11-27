# Upgrade

## From 0.7 to 0.8

Every setting is now **per site** and lives under `system`. A setting at the root of `masilia_ibexa_blocks` is refused when the container is compiled. Move each section under `system.default` (it is the global configuration: what all the sites share), and what differs for a site under its group (`<identifier>_group` with `masilia/ibexa-sites`) or siteaccess:

```yaml
# before
masilia_ibexa_blocks:
    forms: { from_address: contact@example.org }
    newsletter: { enabled: true, subject: 'Confirm' }
    search: { page: { items: 10 } }

# after
masilia_ibexa_blocks:
    system:
        default:
            forms: { from_address: contact@example.org }
            newsletter: { enabled: true, subject: 'Confirm' }
            search: { page: { items: 10 } }
        fossil_exit_group:
            newsletter: { subject: 'Confirm your subscription to Fossil Exit' }
```

The sections are `conventions`, `model`, `themes`, `blocks`, `search`, `fields`, `forms`, `newsletter` and `rate_limit`. A project that already uses `system` for `favicons`, `layout_remote_id` or `layout_menus` only adds `default`.

What to know:

- **Order**: registry of the sites (`site_email` is the sender of the forms and of the newsletter) → siteaccess → group → `default` → default of the code, setting by setting. A list or a table (`sorts`, `facets`, `variations`, `items_field_by_type`, `auto_item_sort_fields`) is replaced as a whole by the more specific scope, not merged. With `masilia/ibexa-sites`, a site whose registry has an email now sends its mails from it: before, every site sent from `forms.from_address`.
- **Newsletter**: its services and routes exist once one scope has `newsletter.enabled: true`; the other sites get a `404` on the routes. The rate limit is read per site, and `symfony/rate-limiter` missing is reported at the first request that has a limit, no longer when the container is compiled.
- **Packages that add directories** (`themes.directories`, `model.directories`, by `prependExtensionConfig`) write `['system' => ['default' => ['model' => ['directories' => [...]]]]]`.
- **PHP**: `Conventions`, `SearchSettings`, `FormSettings`, `ContactSettings` and `NewsletterSettings` are not services any more, and the `masilia_blocks.conventions|search|forms|newsletter|fields.*|blocks.*` container parameters are gone: inject `Masilia\Bundle\IbexaBlocks\Site\SettingsProvider` and call `conventions()`, `search()`, `forms()`… when you need them (do not keep the result in a property of a service: it is the site of one request). A Twig global that gave `SearchSettings` gives the provider instead (`masilia_settings.search.popularSearches`).
- Commands (`theme:check`, `model:install`) read the settings of the default siteaccess; pass `--siteaccess` for another.

## From 0.6 to 0.7

Three changes of the base layout (`@MasiliaIbexaBlocks/layout.twig`) and of `theme.yaml` can break a theme. Run `bin/console masilia:blocks:theme:check <design>` after the upgrade.

### 1. `seo_metas` no longer wraps the icons and the fonts

The `<head>` of the layout is now made of sibling blocks: `favicons`, `preconnect`, `fonts`, `seo_metas` (the SEO metas only), `head_extra`, `stylesheets`.

A theme that overrode `seo_metas` to change the icons or the fonts overrides `favicons` or `fonts` instead.

```twig
{# before #}
{% block seo_metas %}
    <link rel="icon" href="{{ asset('build/images/site/logo.svg') }}">
    {% include "@NovaeZSEO/seometas_head.html.twig" %}
{% endblock %}

{# after #}
{% block favicons %}
    <link rel="icon" href="{{ asset('build/images/site/logo.svg') }}">
{% endblock %}
```

A theme that only wants other icons does not need Twig any more: say `favicons` in its `theme.yaml` (see 2).

### 2. `theme_layout('favicons')` is a list of links

It was the path of the folder of icons; it is now the list of the links to draw (`rel`, `href`, and `sizes` and `type` when there are some). A theme whose layout builds the paths itself (`favicons ~ '/favicon.ico'`) loops over the list:

```twig
{# before #}
<link rel="icon" href="{{ asset(favicons ~ '/favicon.ico') }}">

{# after #}
{% for icon in favicons %}
    <link rel="{{ icon.rel }}"{% if icon.type is defined %} type="{{ icon.type }}"{% endif %}{% if icon.sizes is defined %} sizes="{{ icon.sizes }}"{% endif %} href="{{ asset(icon.href) }}">
{% endfor %}
```

In `theme.yaml`, `favicons` is still a folder (only the files that are there are linked, under their exact name), and can also be a list of links, or `''` for no icon at all. A site can have its own with `masilia_ibexa_blocks.system.<siteaccess or group>.favicons`.

### 3. The preconnect links are the theme's to say

The layout used to link `preconnect` to `fonts.googleapis.com` and `fonts.gstatic.com` itself. It now links the origins of the new `preconnect` key of `theme.yaml`. A theme with Google Fonts has to say them, or the links are gone:

```yaml
preconnect:
  - https://fonts.googleapis.com
  - { origin: https://fonts.gstatic.com, crossorigin: true }
fonts:
  - https://fonts.googleapis.com/css2?family=Inter&display=swap
```

`preconnect: []` means none (self-hosted fonts, whose stylesheet is a path in `public/`, need no link). Like the other keys it comes from the first theme of the design that has it: a theme that changes `fonts:` says its `preconnect:` too.

### 4. Unsubscribing asks for a confirmation (newsletter)

The link at the end of the newsletter mails no longer unsubscribes by itself: it shows a page with a button, and the button posts to the same address. Nothing to change to keep it working: without a template of the theme, the page of the package is shown (plain, unstyled). To style it, write `emails/unsubscribe_confirm.html.twig` in the theme: it gets `title`, `message` and `button`, and its `<form method="post" action="">` must post to the current address. The confirmation of a subscription (`/subscription/confirm/{token}`) is unchanged.

### 5. The layout has a `<meta charset>`

It is the first thing in the `<head>`, in the new `charset` block. A theme that added its own `<meta charset>` (in `head_extra`, say) has it twice: remove it, or override `charset` to change it.
