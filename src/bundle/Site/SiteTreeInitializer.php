<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaBlocks\Content\BlocksFolder;
use Masilia\IbexaBlocks\Conventions;
use RuntimeException;
use Throwable;

/**
 * Starts the content tree of a site: its container, its layout, its home page and a media folder. Each content has a
 * remote id made from the name of the site, so running it again creates nothing twice. Uses the repository of Ibexa
 * only: no AI needed.
 */
final readonly class SiteTreeInitializer
{
    public const FOLDER_TYPE = 'folder';
    private const LAYOUT_TYPE = 'layout_config';

    public function __construct(
        private Repository $repository,
        private ConfigResolverInterface $config,
        private Conventions $conventions,
    ) {
    }

    public static function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
    }

    /**
     * @return list<array{what: string, type: string, remoteId: string, exists: bool, contentId: ?int, locationId: ?int, skipped?: string}>
     *
     * @throws RuntimeException When a content type or a location it needs is missing
     */
    public function init(string $name, string $domain, ?int $parentLocationId, ?int $mediaParentLocationId, ?string $language, bool $dryRun): array
    {
        $slug = self::slug($name);
        if ($slug === '') {
            throw new RuntimeException('The name of the site must have letters or digits.');
        }

        return $this->repository->sudo(fn (): array => $this->run($name, $slug, $domain, $parentLocationId, $mediaParentLocationId, $language, $dryRun));
    }

    /** @return list<array{what: string, type: string, remoteId: string, exists: bool, contentId: ?int, locationId: ?int, skipped?: string}> */
    private function run(string $name, string $slug, string $domain, ?int $parentId, ?int $mediaParentId, ?string $language, bool $dryRun): array
    {
        $language ??= $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        $parentId ??= $this->configured('location_ids.content_structure');
        $mediaParentId ??= $this->configured('location_ids.media');
        $types = $this->repository->getContentTypeService();
        foreach ([$this->conventions->siteType, self::LAYOUT_TYPE, $this->conventions->homeType] as $type) {
            try {
                $types->loadContentTypeByIdentifier($type);
            } catch (NotFoundException) {
                throw new RuntimeException(sprintf('There is no content type "%s": run masilia:blocks:model:install first.', $type));
            }
        }
        if ($parentId === null || !$this->locationExists($parentId)) {
            throw new RuntimeException('There is no location to put the site under: give --parent (a location id).');
        }

        $steps = [
            ['site', $this->conventions->siteType, $slug . '_site', fn (): array => ['title' => $name, 'domain' => $domain], fn (array $made) => $parentId],
            ['layout', self::LAYOUT_TYPE, $slug . '_layout_config', fn (): array => ['bo_title' => $name . ' Layout Configuration', 'logo_text' => $this->richText($name)], null],
            ['home page', $this->conventions->homeType, $slug . '_home', fn (): array => ['title' => 'Home', $this->conventions->blocksField => []], null],
        ];
        // The blocks of the home page, in a folder under it (a page of the site gets its own the same way), when the home page can hold it.
        $skipped = [];
        if ($types->loadContentTypeByIdentifier($this->conventions->homeType)->isContainer()) {
            $steps[] = ['blocks folder', $this->conventions->folderType, BlocksFolder::remoteId($slug . '_home'), fn (): array => ['name' => BlocksFolder::name('Home', $this->conventions->blocksFolderSuffix)], fn (array $made) => $made['home page'] ?? null];
        } else {
            $skipped[] = ['what' => 'blocks folder', 'type' => $this->conventions->folderType, 'remoteId' => BlocksFolder::remoteId($slug . '_home'), 'exists' => false, 'contentId' => null, 'locationId' => null, 'skipped' => sprintf('"%s" is not a container: run masilia:blocks:model:install --allow-container, then this command again', $this->conventions->homeType)];
        }
        if ($mediaParentId !== null && $this->locationExists($mediaParentId) && $this->typeExists(self::FOLDER_TYPE)) {
            $steps[] = ['media folder', self::FOLDER_TYPE, $slug . '_media', fn (): array => ['name' => $name . ' Media'], fn (array $made) => $mediaParentId];
        }

        $result = [];
        $made = [];
        $this->repository->beginTransaction();
        try {
            foreach ($steps as [$what, $type, $remoteId, $fields, $parent]) {
                $existing = $this->existing($remoteId);
                if ($existing !== null) {
                    $result[] = ['what' => $what, 'type' => $type, 'remoteId' => $remoteId, 'exists' => true, 'contentId' => $existing[0], 'locationId' => $existing[1]];
                    $made[$what] = $existing[1];
                    continue;
                }
                $parentLocation = $parent !== null ? $parent($made) : ($made['site'] ?? null);
                if ($dryRun || $parentLocation === null) {
                    $result[] = ['what' => $what, 'type' => $type, 'remoteId' => $remoteId, 'exists' => false, 'contentId' => null, 'locationId' => null];
                    continue;
                }
                [$contentId, $locationId] = $this->create($type, $remoteId, $parentLocation, $fields(), $language);
                $made[$what] = $locationId;
                $result[] = ['what' => $what, 'type' => $type, 'remoteId' => $remoteId, 'exists' => false, 'contentId' => $contentId, 'locationId' => $locationId];
            }
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return [...$result, ...$skipped];
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array{0: int, 1: int}
     */
    private function create(string $type, string $remoteId, int $parentLocationId, array $fields, string $language): array
    {
        $contents = $this->repository->getContentService();
        $struct = $contents->newContentCreateStruct($this->repository->getContentTypeService()->loadContentTypeByIdentifier($type), $language);
        $struct->remoteId = $remoteId;
        foreach ($fields as $field => $value) {
            $struct->setField($field, $value);
        }
        $content = $contents->publishVersion($contents->createContent($struct, [$this->repository->getLocationService()->newLocationCreateStruct($parentLocationId)])->versionInfo);
        $locationId = $content->contentInfo->mainLocationId;
        if ($locationId === null) {
            throw new RuntimeException(sprintf('The %s was created without a location.', $type));
        }

        return [(int) $content->contentInfo->id, (int) $locationId];
    }

    /**
     * @return array{0: int, 1: int}|null The content id and the main location id of the content that has this remote id
     */
    private function existing(string $remoteId): ?array
    {
        try {
            $info = $this->repository->getContentService()->loadContentInfoByRemoteId($remoteId);
        } catch (NotFoundException) {
            return null;
        }

        return [(int) $info->id, (int) $info->mainLocationId];
    }

    private function richText(string $text): string
    {
        return sprintf('<?xml version="1.0" encoding="UTF-8"?><section xmlns="http://docbook.org/ns/docbook" version="5.0-variant ezpublish-1.0"><para>%s</para></section>', htmlspecialchars($text, ENT_XML1 | ENT_QUOTES));
    }

    private function typeExists(string $identifier): bool
    {
        try {
            $this->repository->getContentTypeService()->loadContentTypeByIdentifier($identifier);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    private function locationExists(int $id): bool
    {
        try {
            $this->repository->getLocationService()->loadLocation($id);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function configured(string $parameter): ?int
    {
        try {
            return (int) $this->config->getParameter($parameter) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
