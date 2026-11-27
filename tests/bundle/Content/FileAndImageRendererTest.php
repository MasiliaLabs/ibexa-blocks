<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Ibexa\Core\Helper\FieldHelper;
use Ibexa\Core\MVC\Symfony\Routing\Generator\RouteReferenceGenerator;
use Ibexa\Core\MVC\Symfony\Routing\RouteReference;
use Ibexa\Core\MVC\Symfony\Templating\Twig\Extension\ImageExtension;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\BinaryFileRenderer;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\ImageRenderer;
use Masilia\IbexaBlocks\Value\File;
use Masilia\IbexaBlocks\Value\Image;
use Masilia\IbexaBlocks\Value\ImageSet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\RouterInterface;

final class FileAndImageRendererTest extends TestCase
{
    use Fixtures;

    private function contentWith(?object $value, string $fieldType): Content
    {
        $type = $this->type('doc', ['f' => ['type' => $fieldType]]);
        $content = $this->createStub(Content::class);
        $content->method('getContentType')->willReturn($type);
        $content->method('getFieldValue')->willReturn($value);
        $content->method('getVersionInfo')->willReturn(new VersionInfo(['versionNo' => 3]));

        return $content;
    }

    public function testAFileIsDescribedWithTheAbsoluteDownloadLink(): void
    {
        $references = $this->createMock(RouteReferenceGenerator::class);
        $references->expects(self::once())->method('generate')->willReturn(new RouteReference('ibexa.content.download', ['contentId' => 7]));
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://site.test/content/download/7');
        $content = $this->contentWith(new BinaryFileValue(['fileName' => 'report.pdf', 'fileSize' => 1200, 'mimeType' => 'application/pdf']), 'ibexa_binaryfile');

        $file = (new BinaryFileRenderer($router, $references))->render($content, 'f', $content->getContentType()->getFieldDefinition('f'));

        self::assertEquals(new File('report.pdf', 1200, 'application/pdf', 'https://site.test/content/download/7'), $file);
    }

    public function testAFieldWithNoFileIsNull(): void
    {
        $content = $this->contentWith(new BinaryFileValue(), 'ibexa_binaryfile');

        self::assertNull((new BinaryFileRenderer($this->createStub(RouterInterface::class), $this->createStub(RouteReferenceGenerator::class)))->render($content, 'f', $content->getContentType()->getFieldDefinition('f')));
    }

    private function imageRenderer(bool $empty, ?ImageExtension $images = null): ImageRenderer
    {
        $fields = $this->createStub(FieldHelper::class);
        $fields->method('isFieldEmpty')->willReturn($empty);

        return new ImageRenderer($this->createStub(ContentService::class), $fields, $images ?? $this->createStub(ImageExtension::class), new FieldRendererRegistry(new ServiceLocator([])));
    }

    public function testAnEmptyImageFieldIsNull(): void
    {
        $content = $this->contentWith(null, 'ibexa_image');

        self::assertNull($this->imageRenderer(true)->render($content, 'f', $content->getContentType()->getFieldDefinition('f')));
    }

    public function testAnImageGivesTheVariationATemplateAsksForAndOnlyThen(): void
    {
        $type = $this->type('doc', ['f' => ['type' => 'ibexa_image']]);
        $field = new Field(['value' => (object) ['alternativeText' => 'A boat'], 'fieldDefIdentifier' => 'f']);
        $content = $this->createStub(Content::class);
        $content->method('getContentType')->willReturn($type);
        $content->method('getFieldValue')->willReturn(null);
        $content->method('getField')->willReturn($field);
        $content->method('getVersionInfo')->willReturn(new VersionInfo(['versionNo' => 1]));
        $images = $this->createMock(ImageExtension::class);
        $images->expects(self::once())->method('getImageVariation')->willReturn(new \Ibexa\Contracts\Core\Variation\Values\ImageVariation(['uri' => '/var/card/boat.jpg']));

        $set = $this->imageRenderer(false, $images)->render($content, 'f', $type->getFieldDefinition('f'));

        self::assertInstanceOf(ImageSet::class, $set);
        $image = $set->at('card');
        self::assertInstanceOf(Image::class, $image);
        self::assertSame('/var/card/boat.jpg', $image->uri);
        self::assertSame('A boat', $image->alt);
        self::assertSame($image, $set->at('card'), 'a variation is made once');
    }
}
