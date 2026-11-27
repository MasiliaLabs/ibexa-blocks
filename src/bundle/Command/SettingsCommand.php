<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Site\SettingsReport;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'masilia:blocks:settings', description: 'Show what the settings of a site are and where each value comes from (siteaccess, group or default).')]
final class SettingsCommand extends Command
{
    public function __construct(private readonly SettingsReport $report, private readonly ThemeChecker $checker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show every siteaccess (else the one of --siteaccess, or the default one)')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail (exit code 1) on a scope that is no siteaccess nor group, or on an icon that is missing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = 0;

        foreach ($this->report->unknownScopes() as $scope) {
            $io->warning(sprintf('"%s" under masilia_ibexa_blocks.system is no siteaccess, no group of one and not "default": nothing reads it (a typo?).', $scope));
            ++$problems;
        }

        $siteaccesses = $input->getOption('all') ? $this->report->siteaccesses() : [(string) ($input->hasOption('siteaccess') && $input->getOption('siteaccess') ? $input->getOption('siteaccess') : ($this->report->siteaccesses()[0] ?? 'default'))];
        foreach ($siteaccesses as $siteaccess) {
            $io->section($siteaccess);
            $report = $this->report->of($siteaccess);

            $io->table(['Setting', 'Value', 'From'], array_map(
                static fn (string $name, array $row): array => [$name, mb_strimwidth((string) json_encode($row['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 70, '…'), $row['from']],
                array_keys($report['values']),
                array_values($report['values']),
            ));
            foreach ($report['registry'] as $name => $row) {
                $io->writeln(sprintf('  registry of the sites: <info>%s</info> = %s, before %s', $name, json_encode($row['value'], JSON_UNESCAPED_UNICODE), $row['replaces']));
            }

            foreach ($this->checker->faviconIssuesOf($report['values']['favicons']['value'] ?? null) as [$level, $message]) {
                $io->writeln(sprintf('  <%1$s>%2$s %3$s</>', $level === ThemeChecker::ERROR ? 'error' : 'comment', $level, $message));
                if ($level === ThemeChecker::ERROR) {
                    ++$problems;
                }
            }
        }

        return $problems > 0 && $input->getOption('strict') ? Command::FAILURE : Command::SUCCESS;
    }
}
