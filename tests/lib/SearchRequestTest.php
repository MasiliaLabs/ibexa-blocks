<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Search\SearchRequest;
use Masilia\IbexaBlocks\Search\SearchSettings;
use PHPUnit\Framework\TestCase;

final class SearchRequestTest extends TestCase
{
    public function testATermLongerThanTheSiteAllowsIsCutNotSentToTheIndex(): void
    {
        $settings = new SearchSettings(maxTermLength: 20);

        $request = SearchRequest::fromArray(['search' => str_repeat('é', 5000)], $settings);

        self::assertSame(20, mb_strlen($request->term), 'counted in characters, not bytes');
    }

    public function testTheTagsAreCountedAndEachIsBounded(): void
    {
        $settings = new SearchSettings(maxTermLength: 10, maxTags: 3);

        $request = SearchRequest::fromArray(['tags' => ['a', 'b', 'c', 'd', 'e'], 'category' => str_repeat('x', 100)], $settings);
        self::assertSame(['a', 'b', 'c'], $request->tags);
        self::assertSame(10, strlen($request->category));

        $tags = SearchRequest::fromArray(['tags' => [str_repeat('t', 50), ['nested'], 7]], $settings)->tags;
        self::assertSame([str_repeat('t', 10), '7'], $tags, 'what is not a scalar, or is empty, is dropped');
    }

    public function testASettingOfZeroTagsDropsThemAll(): void
    {
        self::assertSame([], SearchRequest::fromArray(['tags' => ['a']], new SearchSettings(maxTags: 0))->tags);
    }

    public function testTheLimitsComeFromTheApiNodeOfTheConfiguration(): void
    {
        $settings = SearchSettings::fromSettings(['api' => ['max_term_length' => 80, 'max_tags' => 4, 'suggestions_cache_ttl' => 300]]);

        self::assertSame(80, $settings->maxTermLength);
        self::assertSame(4, $settings->maxTags);
        self::assertSame(300, $settings->suggestionsCacheTtl);

        $defaults = SearchSettings::fromSettings([]);
        self::assertSame([200, 10, 0], [$defaults->maxTermLength, $defaults->maxTags, $defaults->suggestionsCacheTtl], 'cached for nobody unless the site asks');
    }

    public function testAnImpossibleLimitIsRaisedToTheSmallestThatWorks(): void
    {
        $settings = SearchSettings::fromSettings(['api' => ['max_term_length' => 0, 'max_tags' => -2, 'suggestions_cache_ttl' => -5]]);

        self::assertSame([1, 0, 0], [$settings->maxTermLength, $settings->maxTags, $settings->suggestionsCacheTtl]);
    }
}
