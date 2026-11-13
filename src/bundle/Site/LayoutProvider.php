<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;

/**
 * The content that holds the layout of the current site (its logo, the text of the footer…), found by the remote id that the
 * layout_remote_id setting gives for the siteaccess.
 */
final class LayoutProvider
{
    private ?Content $content = null;
    private bool $loaded = false;

    public function __construct(
        private readonly ConfigResolverInterface $config,
        private readonly ContentService $contents,
        private readonly ContentFactory $factory,
    ) {
    }

    /** The layout content of the site; null when none is set or it cannot be read. */
    public function content(): ?Content
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $remoteId = $this->config->hasParameter('layout_remote_id', 'masilia_ibexa_blocks') ? $this->config->getParameter('layout_remote_id', 'masilia_ibexa_blocks') : null;
            if ($remoteId !== null && $remoteId !== '') {
                try {
                    $this->content = $this->contents->loadContentByRemoteId((string) $remoteId);
                } catch (NotFoundException|UnauthorizedException) {
                    $this->content = null;
                }
            }
        }

        return $this->content;
    }

    public function renderable(): ?RenderableContent
    {
        $content = $this->content();

        return $content === null ? null : $this->factory->fromContent($content);
    }

    /** The type of menu of a part of the layout ("header", "footer", "footer_bottom"). */
    public function menuType(string $part): ?string
    {
        $menus = (array) $this->config->getParameter('layout_menus', 'masilia_ibexa_blocks');

        return isset($menus[$part]) ? (string) $menus[$part] : null;
    }

    /** The root location of the current site. */
    public function rootLocationId(): int
    {
        return (int) $this->config->getParameter('content.tree_root.location_id');
    }
}
