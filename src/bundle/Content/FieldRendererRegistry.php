<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finds the renderer of a field by the type of the field and has it render the value. A type nobody renders is an error
 * (so that it is found when a page is built), or a log and null when the project prefers a page that shows less to a page
 * that does not show.
 */
final class FieldRendererRegistry
{
    /**
     * @param ContainerInterface $renderers   The renderers by field type, built when first used (set by FieldRendererPass)
     * @param SettingsProvider|null $settings fields.unknown_type ("error", "null" or "auto": error in debug) and fields.ignored_types of the site
     */
    public function __construct(
        private readonly ContainerInterface $renderers,
        private readonly ?SettingsProvider $settings = null,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(string $fieldType): bool
    {
        return $this->renderers->has($fieldType);
    }

    /**
     * @return mixed What the field renders to for a template; null for a field with nothing in it, or that is not rendered
     */
    public function render(Content $content, string $identifier): mixed
    {
        $contentType = $content->getContentType();
        $definition = $contentType->getFieldDefinition($identifier);
        if ($definition === null) {
            return null;
        }

        $type = $definition->fieldTypeIdentifier;
        if (in_array($type, $this->ignoredTypes(), true)) {
            return null;
        }
        if (!$this->renderers->has($type)) {
            if ($this->unknownType() === 'error' || ($this->unknownType() === 'auto' && $this->debug)) {
                throw UnsupportedFieldType::of($type, $contentType->identifier, $identifier);
            }
            $this->logger->warning('No renderer for the field type "{type}" (field "{field}" of "{content_type}")', ['type' => $type, 'field' => $identifier, 'content_type' => $contentType->identifier]);

            return null;
        }

        /** @var FieldRenderer $renderer */
        $renderer = $this->renderers->get($type);

        return $renderer->render($content, $identifier, $definition);
    }

    private function unknownType(): string
    {
        return (string) ($this->settings?->get('fields.unknown_type', 'auto') ?? 'auto');
    }

    /** @return list<string> */
    private function ignoredTypes(): array
    {
        return array_values(array_map('strval', (array) ($this->settings?->get('fields.ignored_types', []) ?? [])));
    }
}
