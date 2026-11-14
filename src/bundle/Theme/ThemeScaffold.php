<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Writes a theme on another one: its manifest, a layout on the base layout, and the sources of its assets. */
final readonly class ThemeScaffold
{
    public function __construct(
        private ThemeRegistry $themes,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return array{files: list<string>, next: list<string>} The files written (relative to the project), and what is left to do by hand
     *
     * @throws ThemeNotCreated
     */
    public function create(string $name, string $parent = 'standard', ?string $directory = null): array
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new ThemeNotCreated('The name must be lower case letters, digits and _, starting with a letter.', true);
        }

        $base = rtrim($directory ?? $this->themes->directories()[0] ?? '', '/');
        $themeDir = $base . '/' . $name;
        if ($base === '' || file_exists($themeDir) || $this->themes->manifest($name) !== null) {
            throw new ThemeNotCreated(sprintf('The theme "%s" already exists (%s).', $name, $themeDir));
        }
        $parentAssets = $this->themes->manifest($parent)?->assets;
        if ($this->themes->manifest($parent) === null) {
            throw new ThemeNotCreated(sprintf('The theme "%s" it should extend has no theme.yaml.', $parent));
        }

        $assetsDirectory = 'assets/themes/' . $name;
        $styles = $parentAssets?->styles ?? [];
        $extra = $parentAssets?->extra ?? [];
        $yaml = [
            sprintf('# The theme "%s", built on "%s": it shows the pages as that theme does, and says here and in its templates what differs.', $name, $parent),
            'assets:',
            sprintf('  directory: %s', $assetsDirectory),
            sprintf('  entry: %s', $name),
            sprintf('  styles: [%s]', implode(', ', $styles)),
            sprintf('  extra: [%s]', implode(', ', $extra)),
            '# favicons: build/images/' . $name . '/icons',
            '# fonts:',
            '#   - https://fonts.googleapis.com/css2?family=Inter&display=swap',
            '',
        ];

        $files = [
            $themeDir . '/theme.yaml' => implode("\n", $yaml),
            $themeDir . '/layout.twig' => "{# The base layout is the package's; this theme says what differs in its theme.yaml. #}\n{% extends \"@MasiliaIbexaBlocks/layout.twig\" %}\n",
            $this->projectDir . '/' . $assetsDirectory . '/scss/main.scss' => $this->stylesheet($parentAssets?->directory, $assetsDirectory),
            $this->projectDir . '/' . $assetsDirectory . '/js/main.js' => $this->script($parentAssets?->directory, $assetsDirectory),
        ];
        $written = [];
        foreach ($files as $path => $content) {
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                throw new ThemeNotCreated(sprintf('Cannot create %s.', dirname($path)));
            }
            file_put_contents($path, $content);
            $written[] = str_replace($this->projectDir . '/', '', $path);
        }

        return ['files' => $written, 'next' => [
            sprintf('Add the theme to a design: ibexa_design_engine.design_list.%s: [%s, %s] (and the design of a siteaccess group: ibexa.system.<group>.design: %s).', $name, $name, $parent, $name),
            'Put its favicons in assets/images/' . $name . '/icons and uncomment favicons in its theme.yaml.',
            'Write what differs in its templates, named after the content type: blocks/<type>.twig, cards/<type>.twig, line/<type>.twig, full/<type>.twig (a file of the same name in this theme replaces the one of the theme it extends).',
            'Build the assets: yarn encore dev (the entries are found from the theme.yaml files).',
            sprintf('Check it: bin/console masilia:blocks:theme:check %s', $name),
        ]];
    }

    private function stylesheet(?string $parentDirectory, string $directory): string
    {
        if ($parentDirectory === null) {
            return "// Styles of the theme.\n";
        }

        return sprintf("// The styles of the theme it extends, then what this theme changes.\n@use '%s';\n", self::relative($directory . '/scss', $parentDirectory . '/scss/main'));
    }

    private function script(?string $parentDirectory, string $directory): string
    {
        if ($parentDirectory === null) {
            return "// Scripts of the theme.\n";
        }

        return sprintf("// The scripts of the theme it extends, then what this theme adds.\nimport '%s';\n", self::relative($directory . '/js', $parentDirectory . '/js/main.js'));
    }

    /** The path from a directory to a file, both relative to the project: "../../standard/scss/main". */
    private static function relative(string $fromDirectory, string $to): string
    {
        $from = explode('/', $fromDirectory);
        $target = explode('/', $to);
        while ($from !== [] && $target !== [] && $from[0] === $target[0] && count($target) > 1) {
            array_shift($from);
            array_shift($target);
        }

        return str_repeat('../', count($from)) . implode('/', $target);
    }
}
