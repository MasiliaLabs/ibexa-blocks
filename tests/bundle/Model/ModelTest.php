<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Bundle\Model;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\Bundle\IbexaBlocks\Model\InvalidModel;
use Masilia\Bundle\IbexaBlocks\Model\ModelPlanner;
use Masilia\Bundle\IbexaBlocks\Model\ModelSource;
use Masilia\Bundle\IbexaBlocks\Model\TypeDefinition;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testTheSocleIsValidAndComplete(): void
    {
        $types = (new ModelSource())->socle();

        $this->assertSame(['home_page', 'layout_config', 'page', 'site'], array_map(static fn (TypeDefinition $t): string => $t->identifier, $types));
        foreach ($types as $type) {
            $this->assertNotSame([], $type->fields, $type->identifier);
        }
        $page = array_values(array_filter($types, static fn (TypeDefinition $t): bool => $t->identifier === 'page'))[0];
        $this->assertContains('blocks', $page->fieldIdentifiers());
        $this->assertSame('eng-GB', $page->mainLanguage());
    }

    public function testADefinitionWithAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidModel::class);
        TypeDefinition::fromArray(['identifier' => 'a', 'group' => 'G', 'names' => ['eng-GB' => 'A'], 'colour' => 'red', 'fields' => []], 'a.yaml');
    }

    public function testAFieldNeedsAUniqueIdentifierAndAType(): void
    {
        $base = ['identifier' => 'a', 'group' => 'G', 'names' => ['eng-GB' => 'A']];
        foreach ([
            [['identifier' => 'f', 'type' => 'ibexa_string'], ['identifier' => 'f', 'type' => 'ibexa_string']],
            [['identifier' => 'f']],
        ] as $fields) {
            try {
                TypeDefinition::fromArray($base + ['fields' => $fields], 'a.yaml');
                $this->fail('refused');
            } catch (InvalidModel) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheTypesOfTheProjectAreReadFromItsDirectories(): void
    {
        $one = sys_get_temp_dir() . '/blocks-model-' . bin2hex(random_bytes(4));
        mkdir($one, 0777, true);
        file_put_contents($one . '/hero.yaml', "identifier: hero\ngroup: Blocks\nnames: {eng-GB: Hero}\nfields:\n  - {identifier: title, type: ibexa_string}\n");
        $source = new ModelSource([$one, $one . '/missing']);

        $this->assertSame(['hero'], array_map(static fn (TypeDefinition $t): string => $t->identifier, $source->project()));
        $this->assertSame([], (new ModelSource([]))->project());

        $this->expectException(InvalidModel::class);
        (new ModelSource([$one, $one]))->project();
    }

    public function testEveryBlockOfTheRepositoryIsAllowedOnThePages(): void
    {
        $existing = ['page' => $this->contentType('page', ['blocks' => 'ibexa_object_relation_list'], ['selectionContentTypes' => ['hero']])];
        $plan = $this->planner($existing, ['Blocks'], [], ['cta', 'hero'])->plan([]);

        $this->assertSame([['owner' => 'page', 'field' => 'blocks', 'add' => ['cta']]], $plan->selections);
    }

    public function testThePlanCreatesWhatIsMissingAndLeavesWhatExists(): void
    {
        $definitions = [
            $this->type('hero', 'Blocks', ['title', 'image']),
            $this->type('page', 'Content', ['title', 'blocks', 'extra']),
            $this->type('site', 'Content', ['title']),
        ];
        $existing = [
            'page' => $this->contentType('page', ['title' => 'ibexa_string', 'blocks' => 'ibexa_object_relation_list'], ['selectionContentTypes' => ['paragraph']]),
            'site' => $this->contentType('site', ['title' => 'ibexa_string']),
        ];

        $plan = $this->planner($existing, ['Content'], ['ibexa_string', 'ibexa_object_relation_list'])->plan($definitions);

        $this->assertSame(['Blocks'], $plan->groups);
        $actions = [];
        foreach ($plan->types as $item) {
            $actions[$item['type']->identifier] = [$item['action'], $item['add']];
        }
        $this->assertSame(['create', ['title', 'image']], $actions['hero']);
        $this->assertSame(['complete', ['extra']], $actions['page']);
        $this->assertSame('conform', $actions['site'][0]);
        $this->assertSame([['owner' => 'page', 'field' => 'blocks', 'add' => ['hero']]], $plan->selections);
        $this->assertFalse($plan->isEmpty());
    }

    public function testBlocksAreAllowedOnTheHomePageToo(): void
    {
        $existing = [
            'page' => $this->contentType('page', ['blocks' => 'ibexa_object_relation_list'], ['selectionContentTypes' => ['hero']]),
            'home_page' => $this->contentType('home_page', ['blocks' => 'ibexa_object_relation_list'], ['selectionContentTypes' => ['paragraph']]),
        ];
        $plan = $this->planner($existing, ['Blocks'], ['ibexa_string'])->plan([$this->type('hero', 'Blocks', ['title'])]);

        $this->assertSame([['owner' => 'home_page', 'field' => 'blocks', 'add' => ['hero']]], $plan->selections);
    }

    public function testAnOpenBlocksFieldIsNotRestricted(): void
    {
        $existing = ['page' => $this->contentType('page', ['title' => 'ibexa_string', 'blocks' => 'ibexa_object_relation_list'], ['selectionContentTypes' => []])];
        $plan = $this->planner($existing, ['Blocks', 'Content'], ['ibexa_string'])->plan([$this->type('hero', 'Blocks', ['title'])]);

        $this->assertSame([], $plan->selections);
    }

    public function testAConformingModelHasNothingToDo(): void
    {
        $existing = ['site' => $this->contentType('site', ['title' => 'ibexa_string'])];
        $plan = $this->planner($existing, ['Content'], ['ibexa_string'])->plan([$this->type('site', 'Content', ['title'])]);

        $this->assertTrue($plan->isEmpty());
    }

    public function testAFieldOfAnUninstalledFieldTypeIsSkippedWithANote(): void
    {
        $definition = $this->type('page', 'Content', ['title', 'metas'], ['metas' => 'novaseometas']);
        $plan = $this->planner([], ['Content'], ['ibexa_string'])->plan([$definition]);

        $this->assertSame(['title'], $plan->types[0]['add']);
        $this->assertStringContainsString('novaseometas', $plan->types[0]['notes'][0]);
    }

    /** @param list<string> $fields @param array<string, string> $types */
    private function type(string $identifier, string $group, array $fields, array $types = []): TypeDefinition
    {
        return TypeDefinition::fromArray([
            'identifier' => $identifier, 'group' => $group, 'names' => ['eng-GB' => $identifier],
            'fields' => array_map(static fn (string $f): array => ['identifier' => $f, 'type' => $types[$f] ?? 'ibexa_string'], $fields),
        ], $identifier . '.yaml');
    }

    /** @param array<string, string> $fields @param array<string, mixed> $settings */
    private function contentType(string $identifier, array $fields, array $settings = []): ContentType
    {
        $definitions = [];
        foreach ($fields as $id => $type) {
            $definitions[] = new FieldDefinition(['identifier' => $id, 'fieldTypeIdentifier' => $type, 'fieldSettings' => $id === 'blocks' ? $settings : []]);
        }

        return new ContentType(['identifier' => $identifier, 'fieldDefinitions' => new FieldDefinitionCollection($definitions)]);
    }

    /**
     * @param array<string, ContentType> $existing
     * @param list<string>               $groups     The groups that exist
     * @param list<string>               $fieldTypes The field types that are installed
     * @param list<string>               $repositoryBlocks The types of the group of blocks in the repository
     */
    private function planner(array $existing, array $groups, array $fieldTypes, array $repositoryBlocks = []): ModelPlanner
    {
        $contentTypes = $this->createStub(ContentTypeService::class);
        $contentTypes->method('loadContentTypeByIdentifier')->willReturnCallback(static fn (string $id) => $existing[$id] ?? throw new NotFoundException('type', $id));
        $contentTypes->method('loadContentTypeGroupByIdentifier')->willReturnCallback(static fn (string $id) => in_array($id, $groups, true) ? new ContentTypeGroup() : throw new NotFoundException('group', $id));
        $contentTypes->method('loadContentTypes')->willReturn(array_map(static fn (string $id) => new ContentType(['identifier' => $id]), $repositoryBlocks));
        $fieldTypeService = $this->createStub(FieldTypeService::class);
        $fieldTypeService->method('hasFieldType')->willReturnCallback(static fn (string $t): bool => in_array($t, $fieldTypes, true));
        $repository = $this->createStub(Repository::class);
        $repository->method('sudo')->willReturnCallback(static fn (callable $c) => $c());

        return new ModelPlanner($repository, $contentTypes, $fieldTypeService, new Conventions());
    }
}
