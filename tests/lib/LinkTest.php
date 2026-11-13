<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Value\Link;
use Masilia\IbexaBlocks\Value\Text;
use PHPUnit\Framework\TestCase;

final class LinkTest extends TestCase
{
    public function testALinkAnswersToTheNamesOfAMenuItem(): void
    {
        $link = new Link('/about', 'About', ['identifier' => 'page']);

        self::assertSame('/about', $link->uri);
        self::assertSame('/about', $link->getUri());
        self::assertSame('About', $link->getLabel());
        self::assertSame('page', $link->getExtra('identifier'));
        self::assertNull($link->getExtra('missing'));
        self::assertSame('x', $link->getExtra('missing', 'x'));
    }

    public function testATextPrintsEscapedWithItsLineBreaksAndKeepsTheRawText(): void
    {
        $text = new Text("One <b>\nTwo");

        self::assertSame("One &lt;b&gt;<br />\nTwo", (string) $text);
        self::assertSame("One <b>\nTwo", $text->rawText);
    }
}
