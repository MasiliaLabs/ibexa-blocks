<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Model\InvalidModel;
use Masilia\Bundle\IbexaBlocks\Model\ModelInstaller;
use Masilia\Bundle\IbexaBlocks\Model\ModelPlanner;
use Masilia\Bundle\IbexaBlocks\Model\ModelSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'masilia:blocks:model:install', description: 'Create the content types of the socle (layout, site, home page, page) and of the project (config/content_model), and allow every block on the pages. Never changes or removes what exists.')]
final class InstallModelCommand extends Command
{
    public function __construct(
        private readonly ModelSource $source,
        private readonly ModelPlanner $planner,
        private readonly ModelInstaller $installer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('no-socle', null, InputOption::VALUE_NONE, 'Do not install the socle of the package, only the definitions of the project')
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Install only this content type (repeat it)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $definitions = $input->getOption('no-socle') ? [] : $this->source->socle();
            array_push($definitions, ...$this->source->project());
            if ((array) $input->getOption('only') !== []) {
                $only = array_map('strval', (array) $input->getOption('only'));
                $definitions = array_values(array_filter($definitions, static fn ($d): bool => in_array($d->identifier, $only, true)));
            }
            $plan = $this->planner->plan($definitions);
        } catch (InvalidModel $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        foreach ($plan->groups as $group) {
            $io->writeln(sprintf(' <info>create</info>   group %s', $group));
        }
        foreach ($plan->types as $item) {
            $label = match ($item['action']) {
                'create' => '<info>create</info>  ',
                'complete' => '<comment>complete</comment>',
                default => 'ok       ',
            };
            $io->writeln(sprintf(' %s type %s%s', $label, $item['type']->identifier, $item['add'] !== [] && $item['action'] === 'complete' ? ' + ' . implode(', ', $item['add']) : ''));
            foreach ($item['notes'] as $note) {
                $io->writeln('            <comment>' . $note . '</comment>');
            }
        }
        foreach ($plan->selections as $selection) {
            $io->writeln(sprintf(' <comment>allow</comment>    %s.%s: %s', $selection['owner'], $selection['field'], implode(', ', $selection['add'])));
        }

        if ($plan->isEmpty()) {
            $io->success('Nothing to do: the content model is in place.');

            return Command::SUCCESS;
        }
        if ($input->getOption('dry-run')) {
            $io->note(sprintf('%d change(s) would be made (dry run).', $plan->changes()));

            return Command::SUCCESS;
        }

        try {
            $done = $this->installer->apply($plan);
        } catch (Throwable $e) {
            $io->error(sprintf('%s: %s', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d change(s) made.', count($done)));

        return Command::SUCCESS;
    }
}
