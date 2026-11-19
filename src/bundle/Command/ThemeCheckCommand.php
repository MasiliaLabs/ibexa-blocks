<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaBlocks\Model\InvalidModel;
use Masilia\Bundle\IbexaBlocks\Model\ModelPlanner;
use Masilia\Bundle\IbexaBlocks\Model\ModelSource;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeChecker;
use Masilia\IbexaBlocks\Conventions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'masilia:blocks:theme:check', description: 'Check the themes of a design: every block type shown, templates and asset sources there, layouts, dead files.')]
final class ThemeCheckCommand extends Command
{
    /**
     * @param array<string, list<string>> $designs
     */
    public function __construct(
        private readonly ThemeChecker $checker,
        private readonly ContentTypeService $types,
        private readonly PermissionResolver $permissions,
        private readonly Conventions $conventions,
        private readonly ModelSource $models,
        private readonly ModelPlanner $planner,
        #[Autowire('%ibexa.design.list%')]
        private readonly array $designs,
        #[Autowire('%masilia_blocks.blocks.handled_by_project%')]
        private readonly array $handledByProject = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('design', InputArgument::OPTIONAL, 'The design to check (default: all but the admin)')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail on notices too, not only on errors')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Id of the user whose rights read the content types', '14');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->permissions->setCurrentUserReference(new UserReference((int) $input->getOption('user')));

        $designs = $input->getArgument('design') !== null ? [(string) $input->getArgument('design')] : array_values(array_diff(array_keys($this->designs), ['admin']));
        $blockTypes = [];
        try {
            foreach ($this->types->loadContentTypes($this->types->loadContentTypeGroupByIdentifier($this->conventions->blockGroup)) as $type) {
                if (!in_array($type->identifier, $this->handledByProject, true)) {
                    $blockTypes[] = $type->identifier;
                }
            }
        } catch (NotFoundException) {
            $io->warning(sprintf('There is no group of content types "%s": the block types are not checked.', $this->conventions->blockGroup));
        }
        $contentTypes = [];
        foreach ($this->types->loadContentTypeGroups() as $group) {
            foreach ($this->types->loadContentTypes($group) as $type) {
                $contentTypes[$type->identifier] = true;
            }
        }
        $contentTypes = array_keys($contentTypes);

        $errors = $notices = 0;
        foreach ($designs as $design) {
            if (!isset($this->designs[$design])) {
                $io->error(sprintf('There is no design "%s" (designs: %s).', $design, implode(', ', array_keys($this->designs))));

                return Command::FAILURE;
            }
            $chain = array_values(array_unique([$design, ...$this->designs[$design]]));
            $issues = [...$this->checker->check($chain, $blockTypes, $contentTypes), ...$this->modelIssues($chain)];
            $io->section(sprintf('%s (%s)', $design, implode(' > ', $chain)));
            if ($issues === []) {
                $io->writeln(' <info>ok</info>');
            }
            foreach ($issues as $issue) {
                $error = $issue['level'] === ThemeChecker::ERROR;
                $error ? ++$errors : ++$notices;
                $io->writeln(sprintf(' %s %s%s', $error ? '<error> error </error>' : '<comment>notice</comment>', $issue['theme'] !== null ? '[' . $issue['theme'] . '] ' : '', $issue['message']));
            }
        }

        $io->newLine();
        $io->writeln(sprintf('%d error(s), %d notice(s).', $errors, $notices));

        return $errors > 0 || ($input->getOption('strict') && $notices > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The content types a theme declares (content_types/) that the repository does not have yet, or not in full.
     *
     * @param list<string> $chain
     *
     * @return list<array{level: string, theme: string|null, message: string}>
     */
    private function modelIssues(array $chain): array
    {
        $issues = [];
        foreach ($chain as $theme) {
            if (!$this->models->declares($theme)) {
                continue; // nothing declared: the theme only has templates and assets
            }
            try {
                $definitions = $this->models->theme($theme);
            } catch (InvalidModel $e) {
                $issues[] = ['level' => ThemeChecker::ERROR, 'theme' => $theme, 'message' => $e->getMessage()];
                continue;
            }
            if ($definitions === []) {
                continue;
            }
            foreach ($this->planner->plan($definitions)->types as $item) {
                if ($item['action'] !== 'conform') {
                    $issues[] = ['level' => ThemeChecker::NOTICE, 'theme' => $theme, 'message' => sprintf('content type "%s" is declared in content_types/ but %s: run masilia:blocks:model:install --theme=%s', $item['type']->identifier, $item['action'] === 'create' ? 'does not exist yet' : 'lacks ' . implode(', ', $item['add']), $theme)];
                }
            }
        }

        return $issues;
    }
}
