<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'masilia:blocks:themes', description: 'List the themes of the project, the designs they make up, and the entries their assets are built under.')]
final class ThemesCommand extends Command
{
    /**
     * @param array<string, list<string>> $designs
     */
    public function __construct(
        private readonly ThemeRegistry $themes,
        #[Autowire('%ibexa.design.list%')]
        private readonly array $designs,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Print what the asset build needs: {"entries": {"<entry>.css": "<source>", ...}}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entries = [];
        $owners = [];
        $problems = [];
        foreach ($this->themes->names() as $name) {
            $assets = $this->themes->manifest($name)?->assets;
            if ($assets === null) {
                continue;
            }
            foreach (['css' => $assets->stylesheetSource(), 'js' => $assets->scriptSource()] as $extension => $source) {
                $entry = $assets->entry . '.' . $extension;
                if (isset($entries[$entry]) && $entries[$entry] !== $source) {
                    $problems[] = sprintf('The entry "%s" is built from %s by "%s" and from %s by "%s".', $entry, $entries[$entry], $owners[$entry], $source, $name);
                    continue;
                }
                if (!is_file($this->projectDir . '/' . $source)) {
                    $problems[] = sprintf('Theme "%s": %s does not exist.', $name, $source);
                    continue;
                }
                $entries[$entry] = $source;
                $owners[$entry] = $name;
            }
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode(['entries' => $entries, 'problems' => $problems], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $problems === [] ? Command::SUCCESS : Command::FAILURE;
        }

        $rows = [];
        foreach ($this->designs as $design => $chain) {
            $rows[] = [$design, implode(' > ', array_unique([$design, ...$chain]))];
        }
        $io->section('Designs');
        $io->table(['Design', 'Themes (the first that has a template wins)'], $rows);

        $rows = [];
        foreach ($this->themes->names() as $name) {
            $manifest = $this->themes->manifest($name);
            $views = array_sum(array_map('count', $manifest?->views ?? []));
            $rows[] = [$name, $views, $manifest?->assets?->entry ?? '-'];
        }
        $io->section('Themes with a theme.yaml');
        $io->table(['Theme', 'Views', 'Assets entry'], $rows);
        $io->writeln(sprintf('%d entries to build.', count($entries)));
        foreach ($problems as $problem) {
            $io->error($problem);
        }

        return $problems === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
