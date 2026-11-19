<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Site\SiteCreator;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'masilia:blocks:site:create', description: 'Create a site from start to finish: its content tree (like site:init), then the site in the registry of masilia/ibexa-sites as a draft (or the siteaccess configuration to paste). Never activates it.')]
final class CreateSiteCommand extends Command
{
    public function __construct(
        private readonly SiteCreator $creator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site, e.g. "Acme Shop"')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'The domain of the site, e.g. acme.example.org (also its host unless --host)')
            ->addOption('design', null, InputOption::VALUE_REQUIRED, 'The design of the site: a key of ibexa_design_engine.design_list')
            ->addOption('language', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A language code of the site (repeat it); the first is the main one, at the root of the host')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host that serves the site (default: the domain)')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'The location the site goes under (default: location_ids.content_structure)')
            ->addOption('media-parent', null, InputOption::VALUE_REQUIRED, 'The location the media folder goes under (default: location_ids.media)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = trim((string) $input->getOption('domain'));
        $design = trim((string) $input->getOption('design'));
        if ($domain === '' || $design === '') {
            $io->error('Give --domain=acme.example.org and --design=<design> (a key of ibexa_design_engine.design_list).');

            return Command::INVALID;
        }
        try {
            $result = $this->creator->create(
                (string) $input->getArgument('name'),
                $domain,
                $design,
                array_map('strval', (array) $input->getOption('language')),
                $input->getOption('host') !== null ? (string) $input->getOption('host') : null,
                $input->getOption('parent') !== null ? (int) $input->getOption('parent') : null,
                $input->getOption('media-parent') !== null ? (int) $input->getOption('media-parent') : null,
                (bool) $input->getOption('dry-run'),
            );
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error(sprintf('%s: %s', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }

        if ($result['problems'] !== []) {
            $io->error('masilia/ibexa-sites refuses the site, nothing was written for it:');
            $io->listing($result['problems']);
            $io->writeln('Fix it and run the command again: the content that exists is kept.');

            return Command::FAILURE;
        }
        foreach ($result['tree'] as $step) {
            $state = $step['exists'] ? 'exists ' : ($step['contentId'] === null ? 'would create' : '<info>created</info>');
            $io->writeln(sprintf(' %-12s %-14s %s%s', $state, $step['what'], $step['remoteId'], $step['locationId'] !== null ? ' (location ' . $step['locationId'] . ')' : ''));
        }
        if ($result['registry']) {
            $site = $result['site'];
            $io->writeln(sprintf(' %-12s %-14s %s', $input->getOption('dry-run') ? 'would create' : '<info>draft</info>', 'site', $site?->identifier ?? ''));
            $io->section('Left to do');
            $io->listing([
                sprintf('Check it in the admin (Sites), then activate it: bin/console masilia:sites:set-status %s active (it serves the hosts).', $site?->identifier ?? '<id>'),
                'Make sure the design "' . $design . '" is in ibexa_design_engine.design_list, and that its theme is built (yarn encore dev).',
                'Fill in the layout (logo, menus, footer) in the back office.',
            ]);
        } else {
            $io->section('masilia/ibexa-sites is not installed: the siteaccess configuration to adapt');
            $io->writeln((string) $result['config']);
        }

        return Command::SUCCESS;
    }
}
