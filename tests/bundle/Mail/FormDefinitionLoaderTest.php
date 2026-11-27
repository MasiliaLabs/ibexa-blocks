<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Mail;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Bundle\IbexaBlocks\Mail\FormDefinitionLoader;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Form\FormSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class FormDefinitionLoaderTest extends TestCase
{
    private function loader(\Throwable $failure, LoggerInterface $logger): FormDefinitionLoader
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContent')->willThrowException($failure);
        $services = new RenderableServices(new FieldRendererRegistry(new ServiceLocator([])), $this->createStub(LinkProvider::class), $this->createStub(BreadcrumbProvider::class), $this->createStub(AdminMetaProvider::class), $this->createStub(CacheTagger::class));

        return new FormDefinitionLoader(new ContentFactory($contents, $this->createStub(LocationService::class), $services), new FormSettings(['form']), $logger);
    }

    public function testAFormThatIsNotThereIsUnknownWithoutAFuss(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        self::assertNull($this->loader(new NotFoundException('content', 9), $logger)->load(9));
    }

    public function testAFaultIsStillAnUnknownFormForTheVisitorButTheLogSaysWhy(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('could not be read'),
            self::callback(static fn (array $context): bool => $context['id'] === 9 && $context['message'] === 'database gone'),
        );

        self::assertNull($this->loader(new \RuntimeException('database gone'), $logger)->load(9));
    }
}
