<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Model\ModelInstaller;
use Masilia\Bundle\IbexaBlocks\Model\ModelPlanner;
use Masilia\Bundle\IbexaBlocks\Model\ModelSource;
use Masilia\Bundle\IbexaBlocks\Model\InvalidModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'masilia:blocks:model:sync-blocks', description: 'Allow the blocks of the project on the content types they declare (allowed_on), when the blocks field of those types restricts the types. Creates nothing, removes nothing.')]
final class SyncBlocksCommand extends Command
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
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be allowed, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $plan = $this->planner->plan($this->source->project());
        } catch (InvalidModel $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }
        foreach ($plan->selections as $selection) {
            $io->writeln(sprintf(' <comment>allow</comment>    %s.%s: %s', $selection['owner'], $selection['field'], implode(', ', $selection['add'])));
        }
        if ($plan->isEmpty()) {
            $io->success('Every block is already where it says it goes (or the blocks field is not restricted).');

            return Command::SUCCESS;
        }
        if ($input->getOption('dry-run')) {
            $io->note('Dry run: nothing written.');

            return Command::SUCCESS;
        }
        try {
            $this->installer->apply($plan);
        } catch (Throwable $e) {
            $io->error(sprintf('%s: %s', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }
        $io->success('Done.');

        return Command::SUCCESS;
    }
}
