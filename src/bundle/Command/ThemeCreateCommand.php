<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Theme\ThemeNotCreated;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeScaffold;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'masilia:blocks:theme:create', description: 'Create a theme that extends another: its manifest (assets), a layout on the base layout, and its asset sources; templates are found by name, nothing to list.')]
final class ThemeCreateCommand extends Command
{
    public function __construct(
        private readonly ThemeScaffold $scaffold,
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
        try {
            $created = $this->scaffold->create((string) $input->getArgument('name'), (string) $input->getOption('extends'), $input->getOption('directory') !== null ? (string) $input->getOption('directory') : null);
        } catch (ThemeNotCreated $e) {
            $io->error($e->getMessage());

            return $e->invalidName ? Command::INVALID : Command::FAILURE;
        }
        foreach ($created['files'] as $file) {
            $io->writeln(sprintf(' <info>created</info> %s', $file));
        }
        $io->section('Left to do');
        $io->listing($created['next']);

        return Command::SUCCESS;
    }
}
