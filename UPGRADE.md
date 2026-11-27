# Upgrade

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
