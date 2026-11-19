<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

use RuntimeException;

/** A content type definition (the socle or a theme's content_types/) that cannot be read. */
final class InvalidModel extends RuntimeException
{
}
