<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\View;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Ibexa\Core\MVC\Symfony\View\View;
use Ibexa\Core\MVC\Symfony\View\ViewProvider;
use Masilia\Bundle\IbexaBlocks\Controller\BlocksFolderController;
use Masilia\IbexaBlocks\Content\BlocksFolder;
use Masilia\IbexaBlocks\Conventions;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * The folder that holds the blocks of a page is storage, not a page of the site: its full view answers 404 instead of
 * Ibexa's default dump of a folder. It is a folder with the remote id of one of those (see BlocksFolder), or one named like it
 * under a page or the home page. A view the project configures itself (content_view) wins over it.
 */
final readonly class BlocksFolderViewProvider implements ViewProvider
{
    public function __construct(
        private Conventions $conventions,
        private Repository $repository,
    ) {
    }

    public function getView(View $view): ?View
    {
        if (!$view instanceof ContentView || $view->getViewType() !== 'full') {
            return null;
        }
        $content = $view->getContent();
        if ($content === null || $content->getContentType()->identifier !== $this->conventions->folderType) {
            return null;
        }
        if (!BlocksFolder::isRemoteId((string) $content->contentInfo->remoteId) && !$this->namedLikeOneUnderAPage($view, (string) $content->getName())) {
            return null;
        }

        $provided = new ContentView();
        $provided->setControllerReference(new ControllerReference(BlocksFolderController::class . '::notFound'));

        return $provided;
    }

    private function namedLikeOneUnderAPage(ContentView $view, string $name): bool
    {
        $location = $view->getLocation();
        if ($location === null || !BlocksFolder::nameMatches($name, $this->conventions->blocksFolderSuffix)) {
            return false;
        }
        try {
            $parent = $this->repository->getLocationService()->loadLocation((int) $location->parentLocationId);

            return in_array($parent->getContentInfo()->getContentType()->identifier, [$this->conventions->pageType, $this->conventions->homeType], true);
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException|\Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException) {
            return false;
        }
    }
}
