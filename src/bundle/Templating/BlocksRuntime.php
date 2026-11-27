<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Templating;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeSettings;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

/** Shows the blocks of a page, each by its own view (so each is cached and tagged by itself). */
final readonly class BlocksRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private FragmentHandler $fragments,
        private ThemeSettings $settings,
        private Environment $twig,
    ) {
    }

    /**
     * What the themes of the design say about the layout: "assets" (entry, styles, extra), "favicons" (the icon links: rel, href, sizes, type), "fonts", "notifications".
     */
    public function layout(string $name): mixed
    {
        return match ($name) {
            'assets' => $this->settings->assets(),
            'favicons' => $this->settings->faviconLinks(),
            'fonts' => $this->settings->fonts(),
            'notifications' => $this->settings->notifications(),
            default => throw new \InvalidArgumentException(sprintf('The layout has no "%s" (known: assets, favicons, fonts, notifications).', $name)),
        };
    }

    /** The in-context editing toolbar, when the toolbox is installed and someone is signed in; nothing otherwise. */
    public function toolbox(): string
    {
        if (!$this->twig->getFunction('masilia_editorial_toolbox')) {
            return '';
        }

        return $this->twig->createTemplate('{% if app.user %}{{ masilia_editorial_toolbox() }}{% endif %}')->render();
    }

    /**
     * @param iterable<mixed>      $blocks   The blocks of the page: renderable contents (anything else is skipped)
     * @param array<string, mixed> $params   Parameters every block is shown with (the breadcrumb, the current location…)
     * @param string               $viewType The view type the blocks are shown in
     */
    public function render(iterable $blocks, array $params = [], string $viewType = 'blocks'): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if (!$block instanceof RenderableContent) {
                continue;
            }
            $html .= $this->fragments->render(new ControllerReference('ibexa_content::viewAction', [
                'contentId' => $block->id(),
                'viewType' => $viewType,
                'params' => $params,
            ]));
        }

        return $html;
    }
}
