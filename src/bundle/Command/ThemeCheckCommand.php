<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Command;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeChecker;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
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
        private readonly SettingsProvider $provider,
        private readonly ThemeRegistry $registry,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%ibexa.design.list%')]
        private readonly array $designs,
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
            foreach ($this->types->loadContentTypes($this->types->loadContentTypeGroupByIdentifier($this->conventions()->blockGroup)) as $type) {
                if (!in_array($type->identifier, $this->provider->handledByProject(), true)) {
                    $blockTypes[] = $type->identifier;
                }
            }
        } catch (NotFoundException) {
            $io->warning(sprintf('There is no group of content types "%s": the block types are not checked.', $this->conventions()->blockGroup));
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
            $issues = $this->checker->check($chain, $blockTypes, $contentTypes);
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

        foreach (array_diff($this->registry->names(), ...array_values($this->designs), ...[array_keys($this->designs)]) as $theme) {
            ++$notices;
            $io->writeln(sprintf(' <comment>notice</comment> [%s] the theme is in no design: add ibexa_design_engine.design_list.%s: [%s] (the list of designs stays yours).', $theme, $theme, $theme));
        }

        foreach ($this->unbuiltEntries($designs) as $entry) {
            ++$errors;
            $io->writeln(sprintf(' <error> error </error> the entry "%s" is not in public/build/entrypoints.json: the pages of a site that uses this theme answer 500. Add the themes to webpack.config.js (require(\'./assets/masilia-themes.js\')(Encore, __dirname);, see the README) and run yarn encore dev.', $entry));
        }

        $io->newLine();
        $io->writeln(sprintf('%d error(s), %d notice(s).', $errors, $notices));

        return $errors > 0 || ($input->getOption('strict') && $notices > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The entries of the themes of these designs that the build of the assets does not know. Nothing is said when there is no build
     * yet (public/build/entrypoints.json absent: the assets were never built, which is another thing to do first).
     *
     * @param list<string> $designs
     *
     * @return list<string>
     */
    private function unbuiltEntries(array $designs): array
    {
        $file = $this->projectDir . '/public/build/entrypoints.json';
        if (!is_file($file)) {
            return [];
        }
        $built = array_keys((array) (json_decode((string) file_get_contents($file), true)['entrypoints'] ?? []));

        $missing = [];
        foreach ($designs as $design) {
            foreach (array_unique([$design, ...($this->designs[$design] ?? [])]) as $theme) {
                $assets = $this->registry->manifest($theme)?->assets;
                if ($assets === null) {
                    continue;
                }
                foreach (['css', 'js'] as $extension) {
                    $entry = $assets->entry . '.' . $extension;
                    if (!in_array($entry, $built, true) && !in_array($entry, $missing, true)) {
                        $missing[] = $entry;
                    }
                }
            }
        }

        return $missing;
    }

    private function conventions(): Conventions
    {
        return $this->provider->conventions();
    }
}
