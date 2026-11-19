<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Bundle\Model;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\ContentTypeDraft;
use Ibexa\Core\Repository\Values\ContentType\ContentTypeCreateStruct;
use Ibexa\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinitionCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinitionUpdateStruct;
use Masilia\Bundle\IbexaBlocks\Model\ModelInstaller;
use Masilia\Bundle\IbexaBlocks\Model\ModelPlan;
use Masilia\Bundle\IbexaBlocks\Model\TypeDefinition;
use Masilia\Bundle\IbexaBlocks\Site\SiteTreeInitializer;
use PHPUnit\Framework\TestCase;

final class ModelInstallerTest extends TestCase
{
    public function testItCreatesTheTypeWithItsFieldsAndPublishesIt(): void
    {
        $definition = TypeDefinition::fromArray([
            'identifier' => 'hero', 'group' => 'Blocks', 'names' => ['eng-GB' => 'Hero'], 'sort' => ['field' => 'published', 'order' => 'DESC'],
            'fields' => [['identifier' => 'title', 'type' => 'ibexa_string', 'name' => 'Title', 'required' => true, 'position' => 10]],
        ], 'hero.yaml');
        $created = null;
        $service = $this->service();
        $service->expects($this->once())->method('createContentType')->willReturnCallback(function (ContentTypeCreateStruct $struct, array $groups) use (&$created) {
            $created = [$struct, $groups];

            return new ContentTypeDraft(['innerContentType' => new ContentType(['identifier' => 'hero'])]);
        });
        $service->expects($this->once())->method('publishContentTypeDraft');

        $done = (new ModelInstaller($this->repository(), $service))->apply(new ModelPlan([], [['type' => $definition, 'action' => 'create', 'add' => ['title'], 'notes' => []]], []));

        $this->assertSame(['type "hero" created (1 fields)'], $done);
        [$struct] = $created;
        $this->assertSame('eng-GB', $struct->mainLanguageCode);
        $this->assertSame('<title>', $struct->nameSchema);
        $field = $struct->fieldDefinitions[0];
        $this->assertSame(['eng-GB' => 'Title'], $field->names, 'the "*" language is the main language of the type');
        $this->assertTrue($field->isRequired);
    }

    public function testItAddsTheBlocksToTheSelectionWithoutLosingTheOnesThatAreThere(): void
    {
        $field = new FieldDefinition(['identifier' => 'blocks', 'fieldTypeIdentifier' => 'ibexa_object_relation_list', 'fieldSettings' => ['selectionContentTypes' => ['paragraph'], 'selectionLimit' => 20]]);
        $page = new ContentType(['identifier' => 'page', 'fieldDefinitions' => new FieldDefinitionCollection([$field])]);
        $updated = null;
        $service = $this->service();
        $service->method('loadContentTypeByIdentifier')->willReturn($page);
        $service->method('createContentTypeDraft')->willReturn(new ContentTypeDraft(['innerContentType' => $page]));
        $service->expects($this->once())->method('updateFieldDefinition')->willReturnCallback(function ($draft, $definition, FieldDefinitionUpdateStruct $update) use (&$updated): void {
            $updated = $update->fieldSettings;
        });

        (new ModelInstaller($this->repository(), $service))->apply(new ModelPlan([], [], [['owner' => 'page', 'field' => 'blocks', 'add' => ['hero', 'paragraph']]]));

        $this->assertSame(['paragraph', 'hero'], $updated['selectionContentTypes']);
        $this->assertSame(20, $updated['selectionLimit']);
    }

    public function testTheContentOfASiteHasStableRemoteIds(): void
    {
        $this->assertSame('acme_shop', SiteTreeInitializer::slug('Acme Shop!'));
        $this->assertSame('', SiteTreeInitializer::slug('  --  '));
    }

    private function repository(): Repository
    {
        $repository = $this->createStub(Repository::class);
        $repository->method('sudo')->willReturnCallback(static fn (callable $c) => $c());

        return $repository;
    }

    private function service(): ContentTypeService
    {
        $service = $this->createMock(ContentTypeService::class);
        $service->method('newContentTypeCreateStruct')->willReturnCallback(static fn (string $id) => new ContentTypeCreateStruct(['identifier' => $id, 'fieldDefinitions' => []]));
        $service->method('newFieldDefinitionCreateStruct')->willReturnCallback(static fn (string $id, string $type) => new FieldDefinitionCreateStruct(['identifier' => $id, 'fieldTypeIdentifier' => $type]));
        $service->method('newFieldDefinitionUpdateStruct')->willReturn(new FieldDefinitionUpdateStruct());
        $service->method('loadContentTypeGroupByIdentifier')->willReturn(new ContentTypeGroup(['identifier' => 'Blocks']));

        return $service;
    }
}
