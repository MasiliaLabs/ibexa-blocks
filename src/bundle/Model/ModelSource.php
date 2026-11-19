<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Where the content types to install are: the socle of the package (layout, site, home page, page), and the
 * content_types/ folder of a theme (its blocks, its block items, its own content).
 */
final readonly class ModelSource
{
    public const THEME_FOLDER = 'content_types';

    public function __construct(
        private ThemeRegistry $themes,
        private string $socleDirectory = __DIR__ . '/../Resources/config/model',
    ) {
    }

    /**
     * @return list<TypeDefinition>
     *
     * @throws InvalidModel
     */
    public function socle(): array
    {
        return $this->read($this->socleDirectory);
    }

    /** Whether the theme has a content_types/ folder */
    public function declares(string $theme): bool
    {
        foreach ($this->themes->directories() as $directory) {
            if (is_dir(sprintf('%s/%s/%s', rtrim($directory, '/'), $theme, self::THEME_FOLDER))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<TypeDefinition>
     *
     * @throws InvalidModel
     */
    public function theme(string $theme): array
    {
        foreach ($this->themes->directories() as $directory) {
            $folder = sprintf('%s/%s/%s', rtrim($directory, '/'), $theme, self::THEME_FOLDER);
            if (is_dir($folder)) {
                return $this->read($folder);
            }
        }
        if ($this->themes->manifest($theme) === null) {
            throw new InvalidModel(sprintf('The theme "%s" does not exist (no theme.yaml in %s).', $theme, implode(', ', $this->themes->directories())));
        }

        return [];
    }

    /**
     * @return list<TypeDefinition>
     */
    private function read(string $directory): array
    {
        $files = glob($directory . '/*.yaml') ?: [];
        sort($files);
        $types = [];
        $seen = [];
        foreach ($files as $file) {
            try {
                $data = Yaml::parseFile($file);
            } catch (ParseException $e) {
                throw new InvalidModel(sprintf('%s is not valid YAML: %s', $file, $e->getMessage()), 0, $e);
            }
            if (!is_array($data)) {
                throw new InvalidModel(sprintf('%s must be a map.', $file));
            }
            $type = TypeDefinition::fromArray($data, basename($file));
            if (isset($seen[$type->identifier])) {
                throw new InvalidModel(sprintf('%s: the content type "%s" is defined twice.', basename($file), $type->identifier));
            }
            $seen[$type->identifier] = true;
            $types[] = $type;
        }

        return $types;
    }
}
