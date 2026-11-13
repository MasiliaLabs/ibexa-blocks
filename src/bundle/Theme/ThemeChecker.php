<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;

/**
 * What is wrong, or only worth knowing, about the themes of a design: a block type no theme shows, a template a manifest names
 * that is not there, assets with no source, a layout that copies the base one, files no one refers to. It reads the files of the
 * themes and the manifests; it changes nothing.
 */
final readonly class ThemeChecker
{
    public const ERROR = 'error';
    public const NOTICE = 'notice';

    public function __construct(
        private ThemeRegistry $themes,
        private string $projectDir,
        /** Directories (besides the themes) whose files may refer to templates: the code of providers, say */
        private array $referenceDirs = [],
    ) {
    }

    /**
     * @param list<string> $chain      The themes of the design, the first wins
     * @param list<string> $blockTypes The content types that are blocks (what "blocks" must show)
     *
     * @return list<array{level: string, theme: string|null, message: string}>
     */
    public function check(array $chain, array $blockTypes): array
    {
        $issues = [];
        $add = static function (string $level, ?string $theme, string $message) use (&$issues): void {
            $issues[] = ['level' => $level, 'theme' => $theme, 'message' => $message];
        };

        $manifests = [];
        foreach ($chain as $theme) {
            try {
                $manifests[$theme] = $this->themes->manifest($theme);
            } catch (InvalidThemeManifest $e) {
                $add(self::ERROR, $theme, $e->getMessage());
            }
        }

        // What each theme says: the template exists on disk (in the theme, or one below it that the design will find).
        $referenced = [];
        foreach ($manifests as $theme => $manifest) {
            foreach ($manifest?->views ?? [] as $viewType => $types) {
                foreach ($types as $type => $definition) {
                    $referenced[$definition->template] = true;
                    if ($this->find($chain, $definition->template) === null) {
                        // Only a notice: a theme names a view for every design that builds on it, and one whose themes lack the file only
                        // matters if its pages hold that content type.
                        $add(self::NOTICE, $theme, sprintf('views.%s.%s: the template "%s" is in no theme of this design (fine if none of its pages shows a "%s").', $viewType, $type, $definition->template, $type));
                    }
                }
            }
        }

        // What must be shown: every block type has a template in the "blocks" view of some theme of the design.
        foreach ($blockTypes as $type) {
            $shown = false;
            foreach ($manifests as $manifest) {
                $shown = $shown || $manifest?->view('blocks', $type) !== null || $manifest?->view('blocks', '*') !== null;
            }
            if (!$shown) {
                $add(self::ERROR, null, sprintf('The block type "%s" is shown by no theme of the design: a page that holds one answers 404.', $type));
            }
        }

        // Assets: a source for each entry.
        foreach ($manifests as $theme => $manifest) {
            $assets = $manifest?->assets;
            if ($assets === null) {
                continue;
            }
            foreach ([$assets->stylesheetSource(), $assets->scriptSource()] as $source) {
                if (!is_file($this->projectDir . '/' . $source)) {
                    $add(self::ERROR, $theme, sprintf('assets: %s does not exist, the entry "%s" cannot be built.', $source, $assets->entry));
                }
            }
        }
        if ($this->themes->manifest($chain[0] ?? '') === null && count($chain) > 0) {
            $add(self::NOTICE, $chain[0], 'This theme has no theme.yaml: it can only override the templates of the themes below it.');
        }

        // Layouts: a copy of the base layout is a maintenance cost; say so.
        foreach ($chain as $theme) {
            $layout = $this->file($theme, 'layout.twig');
            if ($layout !== null && !str_contains((string) file_get_contents($layout), 'MasiliaIbexaBlocks/layout.twig')) {
                $add(self::NOTICE, $theme, 'layout.twig does not extend the base layout (@MasiliaIbexaBlocks/layout.twig): it is a copy to keep in step by hand.');
            }
        }

        // Files that nothing refers to.
        $mentions = $this->mentions($chain);
        foreach ($chain as $theme) {
            foreach (['blocks', 'cards', 'line'] as $folder) {
                foreach ($this->directoryFiles($theme, $folder) as $relative) {
                    if (!isset($referenced[$relative]) && !str_contains($mentions, $relative)) {
                        $add(self::NOTICE, $theme, sprintf('%s is referred to by no theme.yaml and by no template or provider (dead, or included by a name built at run time).', $relative));
                    }
                }
            }
        }

        return $issues;
    }

    /** The path of a template of a theme of the chain: the first theme that has it. */
    private function find(array $chain, string $template): ?string
    {
        foreach ($chain as $theme) {
            $file = $this->file($theme, $template);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    private function file(string $theme, string $template): ?string
    {
        foreach ($this->themes->directories() as $directory) {
            $path = sprintf('%s/%s/%s', rtrim($directory, '/'), $theme, $template);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string> Templates ("blocks/hero.twig") of a folder of a theme
     */
    private function directoryFiles(string $theme, string $folder): array
    {
        $found = [];
        foreach ($this->themes->directories() as $directory) {
            foreach (glob(sprintf('%s/%s/%s/*.twig', rtrim($directory, '/'), $theme, $folder)) ?: [] as $path) {
                $found[] = $folder . '/' . basename($path);
            }
        }

        return $found;
    }

    /** All the text that may name a template: the templates of the chain and the files of the reference directories. */
    private function mentions(array $chain): string
    {
        $text = '';
        $roots = $this->referenceDirs;
        foreach ($chain as $theme) {
            foreach ($this->themes->directories() as $directory) {
                $roots[] = rtrim($directory, '/') . '/' . $theme;
            }
        }
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['twig', 'php', 'yaml'], true)) {
                    $text .= "\n" . file_get_contents($file->getPathname());
                }
            }
        }

        return $text;
    }
}
