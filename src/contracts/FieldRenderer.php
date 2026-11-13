<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Turns the value of a field into what a template prints: a string, a list, a link, an image… One renderer per kind of
 * field, declared with {@see AsFieldRenderer}. A renderer is found when a field is first read, not before.
 *
 * What it returns is the contract with the templates of every theme: keep it plain, and the same from one version to the next.
 */
interface FieldRenderer
{
    /**
     * @return mixed null for a field with nothing in it. A closure for a value that needs an argument from the template
     *               (an image takes the name of the variation it is shown at).
     */
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed;
}
