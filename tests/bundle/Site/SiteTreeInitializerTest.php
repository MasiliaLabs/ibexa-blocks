<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Bundle\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Repository\Values\Content\Location;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Masilia\Bundle\IbexaBlocks\Site\SiteTreeInitializer;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SiteTreeInitializerTest extends TestCase
{
    public function testItRefusesWhenTheContentTypesAreMissing(): void
    {
        $types = $this->createStub(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willThrowException(new NotFoundException('type', 'site'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('masilia:blocks:model:install');
        $this->initializer($types, [])->init('Acme', 'acme.org', 2, null, 'eng-GB', false);
    }

    public function testADryRunWritesNothingAndSaysWhatWouldBeCreated(): void
    {
        $steps = $this->initializer($this->types(), [])->init('Acme Shop', 'acme.org', 2, null, 'eng-GB', true);

        $this->assertSame(['acme_shop_site', 'acme_shop_layout_config', 'acme_shop_home', 'acme_shop_home_blocks'], array_column($steps, 'remoteId'));
        $this->assertSame([false, false, false, false], array_column($steps, 'exists'));
        $this->assertSame([null, null, null, null], array_column($steps, 'contentId'));
    }

    public function testWhatExistsIsLeftAlone(): void
    {
        $steps = $this->initializer($this->types(), ['acme_site' => [10, 20], 'acme_layout_config' => [11, 21], 'acme_home' => [12, 22], 'acme_home_blocks' => [13, 23]])->init('Acme', 'acme.org', 2, null, 'eng-GB', false);

        $this->assertSame([true, true, true, true], array_column($steps, 'exists'));
        $this->assertSame([22], array_values(array_filter(array_column($steps, 'locationId'), static fn (?int $l): bool => $l === 22)));
    }

    private function types(): ContentTypeService
    {
        $types = $this->createStub(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturn(new ContentType(['identifier' => 'any']));

        return $types;
    }

    /** @param array<string, array{0: int, 1: int}> $existing remote id => [content id, location id] */
    private function initializer(ContentTypeService $types, array $existing): SiteTreeInitializer
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContentInfoByRemoteId')->willReturnCallback(static fn (string $r) => isset($existing[$r])
            ? new ContentInfo(['id' => $existing[$r][0], 'mainLocationId' => $existing[$r][1]])
            : throw new NotFoundException('content', $r));
        $locations = $this->createStub(LocationService::class);
        $locations->method('loadLocation')->willReturn(new Location(['id' => 2]));
        $repository = $this->createStub(Repository::class);
        $repository->method('sudo')->willReturnCallback(static fn (callable $c) => $c());
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentTypeService')->willReturn($types);
        $repository->method('getLocationService')->willReturn($locations);

        return new SiteTreeInitializer($repository, $this->createStub(ConfigResolverInterface::class), new Conventions());
    }
}
