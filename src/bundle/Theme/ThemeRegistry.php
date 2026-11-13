<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;
use Masilia\IbexaBlocks\Theme\ThemeManifest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The themes of the project: a folder per theme, with a theme.yaml, in one of the directories that are searched (the
 * templates/themes of the project by default). A theme is read when it is first asked for, and once.
 */
final class ThemeRegistry
{
    public const FILE = 'theme.yaml';

    /** @var array<string, ThemeManifest|null> */
    private array $manifests = [];

    /**
     * @param list<string> $directories Where the themes are: <directory>/<theme>/theme.yaml
     */
    public function __construct(
        #[Autowire('%masilia_blocks.themes.directories%')]
        private readonly array $directories,
    ) {
    }

    /** @return list<string> */
    public function directories(): array
    {
        return $this->directories;
    }

    /** The manifest of a theme; null for a theme that has none (it then only overrides templates, which needs nothing said). */
    public function manifest(string $theme): ?ThemeManifest
    {
        if (!array_key_exists($theme, $this->manifests)) {
            $this->manifests[$theme] = $this->load($theme);
        }

        return $this->manifests[$theme];
    }

    /**
     * @return list<string> The themes that have a theme.yaml
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/*/' . self::FILE) ?: [] as $file) {
                $names[basename(dirname($file))] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    private function load(string $theme): ?ThemeManifest
    {
        foreach ($this->directories as $directory) {
            $file = sprintf('%s/%s/%s', rtrim($directory, '/'), $theme, self::FILE);
            if (!is_file($file)) {
                continue;
            }
            try {
                $data = Yaml::parseFile($file) ?? [];
            } catch (ParseException $e) {
                throw new InvalidThemeManifest(sprintf('%s is not valid YAML: %s', $file, $e->getMessage()), 0, $e);
            }
            if (!is_array($data)) {
                throw new InvalidThemeManifest(sprintf('%s must be a map.', $file));
            }

            return ThemeManifest::fromArray($theme, $data);
        }

        return null;
    }
}
