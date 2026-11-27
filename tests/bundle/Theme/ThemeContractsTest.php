<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\Bundle\Theme;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Newsletter\StoredSubscribers;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriberStore;
use Masilia\Bundle\IbexaBlocks\Search\ContentLister;
use Masilia\Bundle\IbexaBlocks\Search\ListedContents;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeCatalog;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeChecker;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeScaffold;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeShownBlocks;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;

/** What the assistant package reads from this one: themes, the views a design shows, the lists, the subscribers. */
final class ThemeContractsTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/blocks-contracts-' . bin2hex(random_bytes(4));
        mkdir($this->project . '/templates/themes/standard/blocks', 0777, true);
        mkdir($this->project . '/templates/themes/standard/cards', 0777, true);
        mkdir($this->project . '/templates/themes/blog/cards', 0777, true);
        file_put_contents($this->project . '/templates/themes/standard/theme.yaml', "assets: { entry: standard }\n");
        file_put_contents($this->project . '/templates/themes/blog/theme.yaml', "assets: { entry: blog }\n");
        foreach (['standard/blocks/hero', 'standard/blocks/cta', 'standard/cards/article', 'blog/cards/event'] as $file) {
            file_put_contents($this->project . '/templates/themes/' . $file . '.twig', $file);
        }
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->project);
    }

    private function registry(): ThemeRegistry
    {
        return new ThemeRegistry([$this->project . '/templates/themes']);
    }

    public function testADesignShowsTheContentTypesOfItsThemesInEachView(): void
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('blog');
        $shown = new ThemeShownBlocks($this->registry(), $config, ['blog' => ['blog', 'standard']]);

        self::assertSame(['cta', 'hero'], $shown->forSiteaccess('site'));
        self::assertSame(['cta', 'hero'], $shown->contentTypes('site', 'blocks'));
        self::assertSame(['article', 'event'], $shown->contentTypes('site', 'block_item'), 'the cards of the theme and of the theme under it');
    }

    private function catalog(): ThemeCatalog
    {
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeGroupByIdentifier')->willThrowException(new NotFoundException('group', 'blocks'));
        $types->method('loadContentTypeGroups')->willReturn([]);
        $registry = $this->registry();

        return new ThemeCatalog(new ThemeChecker($registry, $this->project), new ThemeScaffold($registry, $this->project), $types, TestSettings::fromParameters([]), ['admin' => ['admin'], 'blog' => ['blog', 'standard'], 'standard' => ['standard']]);
    }

    public function testTheDesignsAreListedWithoutTheAdminAndCheckedByName(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['blog' => ['blog', 'standard'], 'standard' => ['standard']], $catalog->designs());
        self::assertIsArray($catalog->check('blog'));
        $this->expectExceptionMessage('There is no design "nope"');
        $catalog->check('nope');
    }

    public function testAThemeIsCreatedOnAnotherAndRefusedWhenItIsNotValid(): void
    {
        $catalog = $this->catalog();

        $created = $catalog->create('shop', 'standard');

        self::assertContains('templates/themes/shop/theme.yaml', $created['files']);
        self::assertNotSame([], $created['next']);
        $this->expectExceptionMessage('already exists');
        $catalog->create('shop', 'standard');
    }

    public function testASubscriberIsReadWithoutItsToken(): void
    {
        $subscriber = (new Subscriber())->setEmail('a@example.org')->setName('Ada')->setConfirmationToken('secret-token')->setConfirmed(true);
        $subscriber->onPrePersist();
        $store = $this->createMock(SubscriberStore::class);
        $store->method('count')->willReturn(1);
        $store->method('latest')->with(5)->willReturn([$subscriber]);
        $subscribers = new StoredSubscribers($store);

        self::assertSame(1, $subscribers->count());
        $row = $subscribers->latest(5)[0];
        self::assertSame(['email', 'name', 'confirmed', 'active', 'subscribed_at'], array_keys($row));
        self::assertSame('a@example.org', $row['email']);
        self::assertStringNotContainsString('secret-token', json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function testAListPreviewGivesTheIdAndTheNameOfWhatTheListShows(): void
    {
        $item = new class {
            public function id(): int { return 12; }
            public function name(): string { return 'First article'; }
        };
        $lister = $this->createMock(ContentLister::class);
        $lister->method('of')->with('article', 3)->willReturn([$item]);

        self::assertSame([['content_id' => 12, 'name' => 'First article']], (new ListedContents($lister))->of('article', 3));
    }
}
