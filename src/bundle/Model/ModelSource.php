<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Where the content types to install are: the socle of the package (layout, site, home page, page), and the
 * directories of definitions of the project (config/content_model, and those a package adds). The types are global to
 * the repository, not to a theme or a siteaccess: a theme only brings templates and assets.
 */
final readonly class ModelSource
{
    /**
     * @param list<string> $directories Directories of *.yaml definitions that are always read (the site adds its own: model.directories); a directory that does not exist is ignored
     */
    public function __construct(
        #[Autowire('%masilia_blocks.model.directories%')]
        private array $directories = [],
        private string $socleDirectory = __DIR__ . '/../Resources/config/model',
        private ?SettingsProvider $settings = null,
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

    /**
     * @return list<TypeDefinition> What the directories of the project define (a type defined twice is an error)
     *
     * @throws InvalidModel
     */
    public function project(): array
    {
        $types = [];
        $seen = [];
        foreach ($this->settings?->modelDirectories($this->directories) ?? $this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            foreach ($this->read($directory) as $type) {
                if (isset($seen[$type->identifier])) {
                    throw new InvalidModel(sprintf('The content type "%s" is defined twice (%s and %s).', $type->identifier, $seen[$type->identifier], $type->source));
                }
                $seen[$type->identifier] = $type->source;
                $types[] = $type;
            }
        }

        return $types;
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
