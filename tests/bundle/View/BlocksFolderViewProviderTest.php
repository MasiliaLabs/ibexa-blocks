<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Bundle\View;

use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Ibexa\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Masilia\Bundle\IbexaBlocks\Controller\BlocksFolderController;
use Masilia\Bundle\IbexaBlocks\View\BlocksFolderViewProvider;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;

final class BlocksFolderViewProviderTest extends TestCase
{
    public function testAFolderWithTheRemoteIdOfABlocksFolderAnswers404(): void
    {
        $view = $this->provider()->getView($this->view('folder', 'acme_home_blocks', 'Whatever'));

        $this->assertNotNull($view);
        $this->assertSame(BlocksFolderController::class . '::notFound', $view->getControllerReference()->controller);
    }

    public function testAFolderNamedLikeOneUnderAPageAnswers404(): void
    {
        $this->assertNotNull($this->provider('page')->getView($this->view('folder', 'by_hand', 'About Blocks')));
        $this->assertNotNull($this->provider('home_page')->getView($this->view('folder', 'by_hand', 'Home Blocks')));
    }

    public function testOtherContentIsLeftAlone(): void
    {
        $this->assertNull($this->provider('page')->getView($this->view('folder', 'media', 'Media')), 'a folder with another name');
        $this->assertNull($this->provider('site')->getView($this->view('folder', 'by_hand', 'Blocks')), 'named like one but not under a page');
        $this->assertNull($this->provider('page')->getView($this->view('article', 'x_blocks', 'About Blocks')), 'not a folder');
        $this->assertNull($this->provider()->getView($this->view('folder', 'acme_home_blocks', 'Home Blocks', 'line')), 'only the full view');
    }

    private function provider(string $parentType = 'page'): BlocksFolderViewProvider
    {
        $parent = new Location(['id' => 5, 'contentInfo' => new ContentInfo(['id' => 2, 'contentType' => new ContentType(['identifier' => $parentType])])]);
        $locations = $this->createStub(LocationService::class);
        $locations->method('loadLocation')->willReturn($parent);
        $repository = $this->createStub(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);

        return new BlocksFolderViewProvider(new Conventions(), $repository);
    }

    private function view(string $type, string $remoteId, string $name, string $viewType = 'full'): ContentView
    {
        $info = new ContentInfo(['id' => 1, 'remoteId' => $remoteId, 'name' => $name]);
        $content = new Content(['versionInfo' => new VersionInfo(['contentInfo' => $info, 'names' => ['eng-GB' => $name], 'initialLanguageCode' => 'eng-GB']), 'contentType' => new ContentType(['identifier' => $type])]);
        $view = new ContentView(null, [], $viewType);
        $view->setContent($content);
        $view->setLocation(new Location(['id' => 9, 'parentLocationId' => 5]));

        return $view;
    }
}
