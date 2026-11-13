<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use PHPUnit\Framework\TestCase;

/**
 * Contents for the tests: a real content type (Ibexa's own value object) and a mocked content that answers with the given
 * field values.
 *
 * @internal test-only
 */
trait Fixtures
{
    /**
     * @param array<string, array{type: string, settings?: array<string, mixed>}> $fields Field identifier => its definition
     */
    private function type(string $identifier, array $fields): ContentType
    {
        $definitions = [];
        foreach ($fields as $fieldIdentifier => $field) {
            $definitions[] = new FieldDefinition([
                'identifier' => $fieldIdentifier,
                'fieldTypeIdentifier' => $field['type'],
                'fieldSettings' => $field['settings'] ?? [],
                'names' => ['eng-GB' => $fieldIdentifier],
                'descriptions' => [],
                'mainLanguageCode' => 'eng-GB',
            ]);
        }

        return new ContentType([
            'identifier' => $identifier,
            'names' => ['eng-GB' => $identifier],
            'descriptions' => [],
            'mainLanguageCode' => 'eng-GB',
            'fieldDefinitions' => new FieldDefinitionCollection($definitions),
        ]);
    }

    /**
     * @param array<string, object|null> $values Field identifier => the Ibexa value of the field
     */
    private function content(ContentType $type, array $values = [], int $id = 7, string $name = 'A content'): Content
    {
        /** @var TestCase $this */
        $info = $this->createMock(ContentInfo::class);
        $info->method('getId')->willReturn($id);
        $info->method('getName')->willReturn($name);
        $info->method('isHidden')->willReturn(false);
        $info->method('isTrashed')->willReturn(false);

        $content = $this->createMock(Content::class);
        $content->method('getContentType')->willReturn($type);
        $content->method('getContentInfo')->willReturn($info);
        $content->method('getFieldValue')->willReturnCallback(static fn (string $identifier) => isset($values[$identifier]) ? new StubValue($values[$identifier]) : null);

        return $content;
    }
}

/**
 * An Ibexa field value that answers with the properties and methods of a plain object: the tests describe a value by what
 * the renderer reads from it.
 *
 * @internal test-only
 */
final class StubValue implements \Ibexa\Contracts\Core\FieldType\Value
{
    public function __construct(private readonly object $wrapped)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->wrapped->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->wrapped->{$name});
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->wrapped->{$name}(...$arguments);
    }

    public function __toString(): string
    {
        return '';
    }
}
