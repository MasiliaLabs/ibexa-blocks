<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'masilia:blocks:theme:create', description: 'Create a theme that extends another: its manifest, a layout on the base layout, and its asset sources.')]
final class ThemeCreateCommand extends Command
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the theme (lower case letters, digits and _)')
            ->addOption('extends', null, InputOption::VALUE_REQUIRED, 'The theme it builds on: its templates and its styles', 'standard')
            ->addOption('directory', null, InputOption::VALUE_REQUIRED, 'Where the themes are (default: the first directory of the settings)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $parent = (string) $input->getOption('extends');
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            $io->error('The name must be lower case letters, digits and _, starting with a letter.');

            return Command::INVALID;
        }

        $base = rtrim((string) ($input->getOption('directory') ?? $this->themes->directories()[0] ?? ''), '/');
        $themeDir = $base . '/' . $name;
        if ($base === '' || file_exists($themeDir) || $this->themes->manifest($name) !== null) {
            $io->error(sprintf('The theme "%s" already exists (%s).', $name, $themeDir));

            return Command::FAILURE;
        }
        $parentAssets = $this->themes->manifest($parent)?->assets;
        if ($this->themes->manifest($parent) === null) {
            $io->error(sprintf('The theme "%s" it should extend has no theme.yaml.', $parent));

            return Command::FAILURE;
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
            '# views:',
            '#   blocks:',
            '#     hero_banner: blocks/hero_banner.twig      # a block this theme shows with a template of its own',
            '',
        ];

        $files = [
            $themeDir . '/theme.yaml' => implode("\n", $yaml),
            $themeDir . '/layout.twig' => "{# The base layout is the package's; this theme says what differs in its theme.yaml. #}\n{% extends \"@MasiliaIbexaBlocks/layout.twig\" %}\n",
            $this->projectDir . '/' . $assetsDirectory . '/scss/main.scss' => $this->stylesheet($parentAssets?->directory, $assetsDirectory),
            $this->projectDir . '/' . $assetsDirectory . '/js/main.js' => $this->script($parentAssets?->directory, $assetsDirectory),
        ];
        foreach ($files as $path => $content) {
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                $io->error(sprintf('Cannot create %s.', dirname($path)));

                return Command::FAILURE;
            }
            file_put_contents($path, $content);
            $io->writeln(sprintf(' <info>created</info> %s', str_replace($this->projectDir . '/', '', $path)));
        }

        $io->section('Left to do');
        $io->listing([
            sprintf('Add the theme to a design: ibexa_design_engine.design_list.%s: [%s, %s] (and the design of a siteaccess group: ibexa.system.<group>.design: %s).', $name, $name, $parent, $name),
            'Put its favicons in assets/images/' . $name . '/icons and uncomment favicons in its theme.yaml.',
            'Build the assets: yarn encore dev (the entries are found from the theme.yaml files).',
            sprintf('Check it: bin/console masilia:blocks:theme:check %s', $name),
        ]);

        return Command::SUCCESS;
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
