<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Bundle\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Masilia\Bundle\IbexaBlocks\Site\SiteCreator;
use Masilia\Bundle\IbexaBlocks\Site\SiteTreeInitializer;
use Masilia\Contracts\IbexaSites\Problem;
use Masilia\Contracts\IbexaSites\Site;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteStatus;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;

final class SiteCreatorTest extends TestCase
{
    public function testWithoutTheRegistryItPrintsTheConfigurationToPaste(): void
    {
        $result = $this->creator(null, true)->create('Acme', 'acme.org', 'acme', ['eng-GB', 'fre-FR'], null, 2, null, true);

        $this->assertFalse($result['registry']);
        $this->assertStringContainsString('list: [acme, acme_fr]', (string) $result['config']);
        $this->assertStringContainsString('design: acme', (string) $result['config']);
        $this->assertStringContainsString('acme_fr: acme.org/fr, languages: fre-FR', (string) $result['config']);
    }

    public function testTheRegistryIsAskedFirstAndARefusalWritesNothing(): void
    {
        $registry = $this->registry([new Problem('design', 'The design "acme" does not exist.')]);

        $result = $this->creator($registry, false)->create('Acme', 'acme.org', 'acme', [], null, 2, null, false);

        $this->assertSame(['design: The design "acme" does not exist.'], $result['problems']);
        $this->assertSame([], $result['tree'], 'no content is made for a site that cannot exist');
        $this->assertSame([], $registry->created);
    }

    public function testADryRunWritesNothing(): void
    {
        $registry = $this->registry([]);

        $result = $this->creator($registry, false)->create('Acme', 'acme.org', 'acme', [], null, 2, null, true);

        $this->assertSame([], $registry->created);
        $this->assertSame([false, false, false], array_column($result['tree'], 'exists'));
    }

    public function testTheSiteIsMadeAsADraftOnTheHomePageAndTheLayout(): void
    {
        $registry = $this->registry([]);

        $result = $this->creator($registry, true)->create('Acme', 'acme.org', 'acme', ['eng-GB'], 'www.acme.org', 2, null, false);

        $this->assertCount(1, $registry->created);
        $site = $registry->created[0];
        $this->assertSame('acme_group', $site->identifier);
        $this->assertSame(SiteStatus::Draft, $site->status);
        $this->assertSame(22, $site->rootLocationId, 'the home page');
        $this->assertSame('acme_layout_config', $site->layoutRemoteId);
        $this->assertSame('www.acme.org', $site->siteaccesses[0]->host);
        $this->assertSame($site, $result['site']);
    }

    /** @param list<Problem> $problems */
    private function registry(array $problems): object
    {
        return new class($problems) implements SiteRegistry {
            /** @var list<Site> */
            public array $created = [];

            /** @param list<Problem> $problems */
            public function __construct(private array $problems)
            {
            }

            public function list(): array { return []; }
            public function find(string $identifier): ?Site { return null; }
            public function check(Site $site, bool $isNew): array { return $this->problems; }
            public function problems(Site $site, bool $isNew): array { return []; }
            public function create(Site $site): Site { return $this->created[] = $site; }
            public function update(Site $site): Site { return $site; }
            public function setStatus(string $identifier, SiteStatus $status): Site { return $this->created[0]; }
            public function delete(string $identifier): void {}
        };
    }

    /** @param bool $exists Whether the content of the tree is already there (then the home page has a location) */
    private function creator(?SiteRegistry $registry, bool $exists): SiteCreator
    {
        $types = $this->createStub(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturn(new ContentType(['identifier' => 'any']));
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContentInfoByRemoteId')->willReturnCallback(static fn (string $r) => $exists
            ? new ContentInfo(['id' => 10, 'mainLocationId' => str_ends_with($r, '_home') ? 22 : 20])
            : throw new NotFoundException('content', $r));
        $locations = $this->createStub(LocationService::class);
        $locations->method('loadLocation')->willReturn(new Location(['id' => 2]));
        $repository = $this->createStub(Repository::class);
        $repository->method('sudo')->willReturnCallback(static fn (callable $c) => $c());
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentTypeService')->willReturn($types);
        $repository->method('getLocationService')->willReturn($locations);

        return new SiteCreator(new SiteTreeInitializer($repository, $this->createStub(ConfigResolverInterface::class), new Conventions()), $registry);
    }
}
