<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The folder of the blocks of a page is not a page of the site: it answers 404. */
final class BlocksFolderController
{
    public function notFound(): never
    {
        throw new NotFoundHttpException('A folder of blocks is not a page.');
    }
}
