<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Site\SiteTreeInitializer;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'masilia:blocks:site:init', description: 'Start the content tree of a site: its container, its layout, its home page and a media folder (needs the content types: masilia:blocks:model:install). Safe to run again.')]
final class InitSiteCommand extends Command
{
    public function __construct(
        private readonly SiteTreeInitializer $initializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site, e.g. "Acme Shop"')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'The domain of the site, e.g. acme.example.org')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'The location the site goes under (default: location_ids.content_structure)')
            ->addOption('media-parent', null, InputOption::VALUE_REQUIRED, 'The location the media folder goes under (default: location_ids.media)')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'The language code of the content, e.g. eng-GB (default: the default language)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be created, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = trim((string) $input->getOption('domain'));
        if ($domain === '') {
            $io->error('Give the domain of the site: --domain=acme.example.org');

            return Command::INVALID;
        }
        try {
            $steps = $this->initializer->init(
                (string) $input->getArgument('name'),
                $domain,
                $input->getOption('parent') !== null ? (int) $input->getOption('parent') : null,
                $input->getOption('media-parent') !== null ? (int) $input->getOption('media-parent') : null,
                $input->getOption('language') !== null ? (string) $input->getOption('language') : null,
                (bool) $input->getOption('dry-run'),
            );
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error(sprintf('%s: %s', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }

        $layout = null;
        $root = null;
        foreach ($steps as $step) {
            $state = $step['exists'] ? 'exists ' : ($step['contentId'] === null ? 'would create' : '<info>created</info>');
            $io->writeln(sprintf(' %-12s %-14s %s%s', $state, $step['what'], $step['remoteId'], $step['locationId'] !== null ? ' (location ' . $step['locationId'] . ')' : ''));
            $layout = $step['what'] === 'layout' ? $step['remoteId'] : $layout;
            $root = $step['what'] === 'home page' ? $step['locationId'] : $root;
        }
        if (!$input->getOption('dry-run')) {
            $io->section('Left to do');
            $io->listing([
                'Declare the site in masilia/ibexa-sites (admin > Sites): root location ' . ($root ?? '?') . ' (the home page), layout "' . ($layout ?? '?') . '", design = your theme, then activate it.',
                'Without ibexa-sites: set masilia_ibexa_blocks.system.<siteaccess group>.layout_remote_id: ' . ($layout ?? '?'),
                'Fill in the layout (logo, menus, footer) in the back office.',
            ]);
        }

        return Command::SUCCESS;
    }
}
