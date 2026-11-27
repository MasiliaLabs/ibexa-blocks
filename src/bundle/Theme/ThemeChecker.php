<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;

/**
 * What is wrong, or only worth knowing, about the themes of a design: a block type no theme shows, a template a manifest names
 * that is not there or that the convention finds anyway, assets with no source, a layout that copies the base one, files no one refers to. It reads the files of the
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
        /** The settings of the site: the templates they name (the one of the Featured list) are used, though no file says so */
        private ?SettingsProvider $settings = null,
    ) {
    }

    /**
     * @param list<string> $chain      The themes of the design, the first wins
     * @param list<string> $blockTypes   The content types that are blocks (what "blocks" must show)
     * @param list<string> $contentTypes Every content type of the site: a template named after one is found by the convention
     *
     * @return list<array{level: string, theme: string|null, message: string}>
     */
    public function check(array $chain, array $blockTypes, array $contentTypes = []): array
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
                    if ($definition->variations === [] && $definition->params === [] && $definition->template === sprintf('%s/%s.twig', BlockRegistry::folders($viewType)[0], $type)) {
                        $add(self::NOTICE, $theme, sprintf('views.%s.%s: "%s" is the template the convention finds without it, the line can go.', $viewType, $type, $definition->template));
                    }
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
            $shown = $this->find($chain, sprintf('%s/%s.twig', BlockRegistry::folders('blocks')[0], $type)) !== null;
            foreach ($manifests as $manifest) {
                $shown = $shown || $manifest?->view('blocks', $type) !== null || $manifest?->view('blocks', '*') !== null;
            }
            if (!$shown) {
                $add(self::ERROR, null, sprintf('The block type "%s" is shown by no theme of the design: a page that holds one answers 404. Add blocks/%1$s.twig to a theme.', $type));
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
        // Favicons: each icon of the site is a file of public/ under that exact name (checked once the assets were built).
        if (is_dir($this->projectDir . '/public/build') && $this->settings !== null && $this->settings->get('favicons') !== null) {
            $site = \Masilia\IbexaBlocks\Theme\ThemeFavicons::fromValue('masilia_ibexa_blocks.favicons', $this->settings->get('favicons'));
            foreach ($this->faviconIssues($site->directory, $site->links) as $issue) {
                $add($issue[0], '(site)', $issue[1]);
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

        // Files that nothing refers to: not named by a theme.yaml, not the template of a content type, not included by a template.
        $mentions = $this->mentions($chain) . "\n" . implode("\n", $this->settings?->configuredTemplates() ?? []);
        foreach ($chain as $theme) {
            $dynamic = $this->dynamicFolders($theme);
            foreach (['blocks', 'cards', 'line', 'embed', 'full'] as $folder) {
                foreach ($this->directoryFiles($theme, $folder) as $relative) {
                    $name = basename($relative, '.twig');
                    if (isset($referenced[$relative]) || isset($dynamic[$folder]) || str_contains($mentions, $relative) || $name === '_default' || in_array($name, $contentTypes, true)) {
                        continue;
                    }
                    $add(self::NOTICE, $theme, sprintf('%s is named after no content type, and no theme.yaml, template or provider refers to it (dead, a typo in the name, or included by a name built at run time).', $relative));
                }
            }
        }

        return $issues;
    }

    /**
     * The problems of the icons a site says (its setting `favicons`: a folder, a list of links or ''), once the assets are built.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function faviconIssuesOf(mixed $value): array
    {
        if ($value === null || !is_dir($this->projectDir . '/public/build')) {
            return [];
        }
        $site = \Masilia\IbexaBlocks\Theme\ThemeFavicons::fromValue('masilia_ibexa_blocks.favicons', $value);

        return $this->faviconIssues($site->directory, $site->links);
    }

    /**
     * @param list<array{rel: string, href: string, sizes?: string, type?: string}>|null $links
     *
     * @return list<array{0: string, 1: string}>
     */
    private function faviconIssues(?string $directory, ?array $links): array
    {
        $issues = [];
        $public = new FaviconSet($this->projectDir . '/public');
        $spelled = static function (array $files, string $name): ?string {
            foreach ($files as $file) {
                if (strcasecmp($file, $name) === 0) {
                    return $file;
                }
            }

            return null;
        };
        $mismatch = static fn (string $path, string $found): string => sprintf('favicons: "%s" is there as "%s": a server that tells upper and lower case apart answers 404; rename the file.', $path, $found);

        if ($directory !== null && $directory !== '') {
            $files = $public->files($directory);
            if ($files === []) {
                $issues[] = [self::ERROR, sprintf('favicons: %s is not a folder of public/ (or is empty).', $directory)];
            }
            $wanted = array_values(array_unique(array_map(static fn (array $f): string => $f[0], FaviconSet::FILES)));
            foreach ($files !== [] ? $wanted : [] as $file) {
                if (in_array($file, $files, true)) {
                    continue;
                }
                $found = $spelled($files, $file);
                $issues[] = $found !== null ? [self::ERROR, $mismatch($directory . '/' . $file, $found)] : [self::NOTICE, sprintf('favicons: %s/%s is not there, its link is left out.', $directory, $file)];
            }
        }
        foreach ($links ?? [] as $link) {
            $folder = dirname($link['href']);
            $files = $public->files($folder);
            if (in_array(basename($link['href']), $files, true)) {
                continue;
            }
            $found = $spelled($files, basename($link['href']));
            $issues[] = $found !== null ? [self::ERROR, $mismatch($link['href'], $found)] : [self::ERROR, sprintf('favicons: %s is not a file of public/.', $link['href'])];
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
     * The folders a template of the theme includes from by a name it builds (`'cards/' ~ type ~ '.twig'`): any file of the folder may be
     * the one included, so none is dead; it counts for this theme only, a theme whose templates name their cards is still checked.
     *
     * @return array<string, true>
     */
    private function dynamicFolders(string $theme): array
    {
        $folders = [];
        foreach ($this->themes->directories() as $directory) {
            $root = rtrim($directory, '/') . '/' . $theme;
            if (!is_dir($root)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'twig' && preg_match_all('#[\'"](?:@ibexadesign/)?(blocks|cards|line|embed|full)/[\'"]\s*~#', (string) file_get_contents($file->getPathname()), $found) > 0) {
                    foreach ($found[1] as $folder) {
                        $folders[$folder] = true;
                    }
                }
            }
        }

        return $folders;
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
