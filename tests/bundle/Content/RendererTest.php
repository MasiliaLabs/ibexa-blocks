<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Masilia\Bundle\IbexaBlocks\Content\Renderer\AuthorRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\CountryRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\KeywordRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\MapLocationRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\MatrixRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\ScalarRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\SelectionRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\TextRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\UrlRenderer;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\IbexaBlocks\Value\Coordinates;
use Masilia\IbexaBlocks\Value\Link;
use Masilia\IbexaBlocks\Value\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    use Fixtures;

    private function render(FieldRenderer $renderer, string $type, ?object $value, array $settings = []): mixed
    {
        $contentType = $this->type('t', ['f' => ['type' => $type, 'settings' => $settings]]);

        return $renderer->render($this->content($contentType, ['f' => $value]), 'f', $contentType->getFieldDefinition('f'));
    }

    /**
     * @return iterable<string, array{0: string, 1: object, 2: mixed}>
     */
    public static function scalars(): iterable
    {
        yield 'string' => ['ibexa_string', (object) ['text' => 'Hello'], 'Hello'];
        yield 'boolean' => ['ibexa_boolean', (object) ['bool' => true], true];
        yield 'integer' => ['ibexa_integer', (object) ['value' => 5], 5];
        yield 'float' => ['ibexa_float', (object) ['value' => 1.5], 1.5];
        yield 'email' => ['ibexa_email', (object) ['email' => 'a@b.c'], 'a@b.c'];
        yield 'time' => ['ibexa_time', (object) ['time' => 3600], 3600];
        yield 'date' => ['ibexa_date', (object) ['date' => 'D'], 'D'];
    }

    #[DataProvider('scalars')]
    public function testAScalarFieldGivesTheOneProperty(string $type, object $value, mixed $expected): void
    {
        self::assertSame($expected, $this->render(new ScalarRenderer(), $type, $value));
    }

    public function testAScalarFieldWithNoValueIsNull(): void
    {
        self::assertNull($this->render(new ScalarRenderer(), 'ibexa_string', null));
    }

    public function testATextIsNullWhenEmptyAndElseAValueThatPrintsWithItsBreaks(): void
    {
        self::assertNull($this->render(new TextRenderer(), 'ibexa_text', (object) ['text' => '']));
        $text = $this->render(new TextRenderer(), 'ibexa_text', (object) ['text' => "a\nb"]);

        self::assertInstanceOf(Text::class, $text);
        self::assertSame("a<br />\nb", (string) $text);
    }

    public function testASelectionGivesTheChosenLabelsKeyedByTheirIndex(): void
    {
        $labels = $this->render(new SelectionRenderer(), 'ibexa_selection', (object) ['selection' => [1, 2]], ['options' => [0 => 'Dark', 1 => 'Light', 2 => 'Auto']]);

        self::assertSame([1 => 'Light', 2 => 'Auto'], $labels);
        self::assertSame('Light', reset($labels));
        self::assertSame([], $this->render(new SelectionRenderer(), 'ibexa_selection', null));
    }

    public function testAMatrixGivesItsRowsAsCellsByColumn(): void
    {
        $row = static fn (array $cells): object => new class($cells) { public function __construct(private array $cells) {} public function getCells(): array { return $this->cells; } };
        $matrix = new class([$row(['text' => 'A', 'url' => '/a']), $row(['text' => 'B', 'url' => '/b'])]) { public function __construct(private array $rows) {} public function getRows(): array { return $this->rows; } };

        self::assertSame([['text' => 'A', 'url' => '/a'], ['text' => 'B', 'url' => '/b']], $this->render(new MatrixRenderer(), 'ibexa_matrix', $matrix));
        self::assertSame([], $this->render(new MatrixRenderer(), 'ibexa_matrix', null));
    }

    public function testAUrlIsALinkWhoseNameIsItsTextOrLink(): void
    {
        $link = $this->render(new UrlRenderer(), 'ibexa_url', (object) ['link' => 'https://x.org', 'text' => 'Read']);

        self::assertEquals(new Link('https://x.org', 'Read'), $link);
        self::assertSame('link', $this->render(new UrlRenderer(), 'ibexa_url', (object) ['link' => '/a', 'text' => null])->name);
        self::assertNull($this->render(new UrlRenderer(), 'ibexa_url', (object) ['link' => null, 'text' => null])->uri, 'a URL field left empty has no uri');
        self::assertNull($this->render(new UrlRenderer(), 'ibexa_url', null));
    }

    public function testCoordinatesAuthorsCountriesAndKeywords(): void
    {
        self::assertEquals(new Coordinates(33.5, -7.6, 'Casablanca'), $this->render(new MapLocationRenderer(), 'ibexa_gmap_location', (object) ['latitude' => 33.5, 'longitude' => -7.6, 'address' => 'Casablanca']));
        self::assertSame(['Said' => 's@x.org', 'Lea' => 'l@x.org'], $this->render(new AuthorRenderer(), 'ibexa_author', (object) ['authors' => [(object) ['name' => 'Said', 'email' => 's@x.org'], (object) ['name' => 'Lea', 'email' => 'l@x.org']]]));
        self::assertSame('Morocco', $this->render(new CountryRenderer(), 'ibexa_country', (object) ['countries' => ['MA' => ['Name' => 'Morocco'], 'FR' => ['Name' => 'France']]]));
        self::assertNull($this->render(new CountryRenderer(), 'ibexa_country', (object) ['countries' => []]));
        self::assertSame('solar,wind', $this->render(new KeywordRenderer(), 'ibexa_keyword', (object) ['values' => ['solar', 'wind']]));
    }

    public function testEveryRendererDeclaresTheTypesItHandles(): void
    {
        $declared = [];
        foreach (glob(__DIR__ . '/../../../src/bundle/Content/Renderer/*.php') as $file) {
            $class = 'Masilia\\Bundle\\IbexaBlocks\\Content\\Renderer\\' . basename($file, '.php');
            $attributes = (new \ReflectionClass($class))->getAttributes(AsFieldRenderer::class);
            self::assertCount(1, $attributes, $class . ' must carry #[AsFieldRenderer]');
            self::assertTrue(is_subclass_of($class, FieldRenderer::class), $class . ' must implement FieldRenderer');
            foreach ($attributes[0]->newInstance()->types as $type) {
                self::assertArrayNotHasKey($type, $declared, sprintf('%s is rendered by %s and %s', $type, $declared[$type] ?? '', $class));
                $declared[$type] = $class;
            }
        }

        foreach (['ibexa_string', 'ibexa_text', 'ibexa_richtext', 'ibexa_image', 'ibexa_object_relation_list', 'ibexa_matrix', 'ibexa_url', 'ibexa_selection', 'ibexa_binaryfile', 'ibexa_gmap_location'] as $type) {
            self::assertArrayHasKey($type, $declared);
        }
    }
}
