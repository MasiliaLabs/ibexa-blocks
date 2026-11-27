<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\UnsupportedFieldType;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class FieldRendererRegistryTest extends TestCase
{
    use Fixtures;

    private function renderer(): FieldRenderer
    {
        return new class() implements FieldRenderer {
            public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
            {
                return strtoupper($identifier . ':' . $definition->fieldTypeIdentifier);
            }
        };
    }

    private function registry(string $unknown = 'auto', array $ignored = [], bool $debug = false, ?AbstractLogger $logger = null): FieldRendererRegistry
    {
        $locator = new ServiceLocator(['ibexa_string' => fn (): FieldRenderer => $this->renderer()]);

        return new FieldRendererRegistry($locator, TestSettings::fromParameters(['masilia_ibexa_blocks:fields.unknown_type' => $unknown, 'masilia_ibexa_blocks:fields.ignored_types' => $ignored]), $debug, $logger ?? new \Psr\Log\NullLogger());
    }

    public function testAFieldIsRenderedByTheRendererOfItsType(): void
    {
        $content = $this->content($this->type('page', ['title' => ['type' => 'ibexa_string']]));

        self::assertSame('TITLE:IBEXA_STRING', $this->registry()->render($content, 'title'));
        self::assertTrue($this->registry()->supports('ibexa_string'));
        self::assertFalse($this->registry()->supports('ibexa_nope'));
    }

    public function testAFieldTheTypeDoesNotHaveIsNull(): void
    {
        self::assertNull($this->registry()->render($this->content($this->type('page', [])), 'title'));
    }

    public function testATypeNobodyRendersIsAnErrorThatSaysWhatToDo(): void
    {
        $content = $this->content($this->type('article', ['author' => ['type' => 'ibexa_user']]));

        $this->expectException(UnsupportedFieldType::class);
        $this->expectExceptionMessage('No renderer for the field type "ibexa_user" (field "author" of "article"). Write one with #[AsFieldRenderer([\'ibexa_user\'])]');

        $this->registry('error')->render($content, 'author');
    }

    public function testInAutoModeItIsAnErrorInDebugAndALogWithNullOtherwise(): void
    {
        $content = $this->content($this->type('article', ['author' => ['type' => 'ibexa_user']]));

        try {
            $this->registry('auto', [], true)->render($content, 'author');
            self::fail('debug refuses it');
        } catch (UnsupportedFieldType) {
            self::assertTrue(true);
        }

        $logged = [];
        $logger = new class($logged) extends AbstractLogger {
            public function __construct(private array &$logged) {}
            public function log($level, \Stringable|string $message, array $context = []): void { $this->logged[] = [$level, $context['type'] ?? '']; }
        };
        self::assertNull($this->registry('auto', [], false, $logger)->render($content, 'author'));
        self::assertSame([['warning', 'ibexa_user']], $logged);
    }

    public function testAnIgnoredTypeIsNullWithoutAnErrorOrALog(): void
    {
        $content = $this->content($this->type('article', ['author' => ['type' => 'ibexa_user']]));

        self::assertNull($this->registry('error', ['ibexa_user'])->render($content, 'author'));
    }
}
