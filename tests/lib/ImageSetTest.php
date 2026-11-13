<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Value\Image;
use Masilia\IbexaBlocks\Value\ImageSet;
use PHPUnit\Framework\TestCase;

final class ImageSetTest extends TestCase
{
    /** @var list<string> */
    private array $made = [];

    private function set(): ImageSet
    {
        return new ImageSet(function (string $variation): ?Image {
            $this->made[] = $variation;

            return $variation === 'missing' ? null : new Image('/img/' . $variation . '.jpg', 'alt of ' . $variation, 'caption');
        });
    }

    public function testAVariationIsAskedForByTheTemplateAndMadeOnce(): void
    {
        $set = $this->set();

        self::assertSame('/img/banner.jpg', $set->at('banner')?->uri);
        self::assertSame('/img/banner.jpg', $set->at('banner')?->uri);
        self::assertSame(['banner'], $this->made);
    }

    public function testWithoutAVariationItIsTheOriginalAndNothingIsMadeUntilItIsRead(): void
    {
        $set = $this->set();
        self::assertSame([], $this->made, 'nothing is made when the image is only passed around');

        self::assertSame('/img/original.jpg', $set->getUri());
        self::assertSame('alt of original', $set->getAlt());
        self::assertSame('caption', $set->getCaption());
        self::assertSame('image', $set->getMediaType());
        self::assertSame(['original'], $this->made);
    }

    public function testAnImageThatIsMissingAtAVariationIsNull(): void
    {
        $set = $this->set();

        self::assertNull($set->at('missing'));
        self::assertSame('', (new ImageSet(static fn (): ?Image => null))->getUri());
    }

    public function testItIsJsonAsItsOriginal(): void
    {
        self::assertSame('{"uri":"\/img\/original.jpg","alt":"alt of original","caption":"caption"}', json_encode($this->set()));
    }

    public function testAnImageThatIsAlreadyAtItsVariationStaysThere(): void
    {
        $image = new Image('/img/card.jpg');

        self::assertSame($image, $image->at('banner'));
    }
}
