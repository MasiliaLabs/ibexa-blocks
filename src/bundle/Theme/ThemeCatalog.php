<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Masilia\Contracts\IbexaBlocks\Themes;
use Masilia\IbexaBlocks\Conventions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** {@see Themes} on the designs of the project, the checker and the scaffold of the theme commands. */
final readonly class ThemeCatalog implements Themes
{
    /**
     * @param array<string, list<string>> $designs
     * @param list<string>                $handledByProject Block types the project renders itself: not required of a theme
     */
    public function __construct(
        private ThemeChecker $checker,
        private ThemeScaffold $scaffold,
        private ContentTypeService $types,
        private Conventions $conventions,
        #[Autowire('%ibexa.design.list%')]
        private array $designs,
        #[Autowire('%masilia_blocks.blocks.handled_by_project%')]
        private array $handledByProject = [],
    ) {
    }

    public function designs(): array
    {
        $designs = [];
        foreach ($this->designs as $design => $themes) {
            if ($design !== 'admin') {
                $designs[$design] = array_values(array_unique([$design, ...$themes]));
            }
        }

        return $designs;
    }

    public function check(string $design): array
    {
        $designs = $this->designs();
        if (!isset($designs[$design])) {
            throw new \InvalidArgumentException(sprintf('There is no design "%s" (designs: %s).', $design, implode(', ', array_keys($designs))));
        }
        $blockTypes = [];
        try {
            foreach ($this->types->loadContentTypes($this->types->loadContentTypeGroupByIdentifier($this->conventions->blockGroup)) as $type) {
                if (!in_array($type->identifier, $this->handledByProject, true)) {
                    $blockTypes[] = $type->identifier;
                }
            }
        } catch (NotFoundException) {
            // No group of blocks: the block types are not checked.
        }
        $contentTypes = [];
        foreach ($this->types->loadContentTypeGroups() as $group) {
            foreach ($this->types->loadContentTypes($group) as $type) {
                $contentTypes[$type->identifier] = true;
            }
        }

        return $this->checker->check($designs[$design], $blockTypes, array_keys($contentTypes));
    }

    public function create(string $name, string $extends = 'standard'): array
    {
        try {
            return $this->scaffold->create($name, $extends);
        } catch (ThemeNotCreated $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }
}
