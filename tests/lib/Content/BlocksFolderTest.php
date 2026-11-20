<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaBlocks\Lib\Content;

use Masilia\IbexaBlocks\Content\BlocksFolder;
use PHPUnit\Framework\TestCase;

final class BlocksFolderTest extends TestCase
{
    public function testTheNameStartsWithThePageAndTheRemoteIdFollowsThePage(): void
    {
        $this->assertSame('About Blocks', BlocksFolder::name('About'));
        $this->assertSame('Blocs', BlocksFolder::name('  ', 'Blocs'));
        $this->assertSame('acme_home_blocks', BlocksFolder::remoteId('acme_home'));
        $this->assertTrue(BlocksFolder::isRemoteId('acme_home_blocks'));
        $this->assertFalse(BlocksFolder::isRemoteId('_blocks'));
        $this->assertFalse(BlocksFolder::isRemoteId('acme_home'));
    }

    public function testAFolderMadeByHandIsRecognisedByItsName(): void
    {
        $this->assertTrue(BlocksFolder::nameMatches('Home Blocks'));
        $this->assertTrue(BlocksFolder::nameMatches('blocks'));
        $this->assertTrue(BlocksFolder::nameMatches('Accueil Blocs', 'Blocs'));
        $this->assertFalse(BlocksFolder::nameMatches('Building blocks of life'));
        $this->assertFalse(BlocksFolder::nameMatches('Media'));
    }
}
