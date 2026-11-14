<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Search;

use Masilia\Contracts\IbexaBlocks\ListPreview;

/** {@see ListPreview} from the lister the front office uses for the blocks that fill themselves. */
final readonly class ListedContents implements ListPreview
{
    public function __construct(private ContentLister $lister)
    {
    }

    public function of(string $contentType, int $limit): array
    {
        return array_map(static fn ($content): array => ['content_id' => $content->id(), 'name' => $content->name()], $this->lister->of($contentType, $limit));
    }
}
