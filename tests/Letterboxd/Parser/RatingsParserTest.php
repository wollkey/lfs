<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Letterboxd\Dto\ParsedRating;
use App\Letterboxd\Parser\FriendsRatingsParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FriendsRatingsParser::class)]
#[CoversClass(ParsedRating::class)]
final class FriendsRatingsParserTest extends TestCase
{
    private FriendsRatingsParser $parser;
    private string $html;

    protected function setUp(): void
    {
        $this->parser = new FriendsRatingsParser();
        $this->html = (string) file_get_contents(__DIR__.'/../Fixtures/friends_film.html');
    }

    public function testParsesEveryRating(): void
    {
        $ratings = $this->parser->parse($this->html);

        self::assertCount(7, $ratings);
        self::assertContainsOnlyInstancesOf(ParsedRating::class, $ratings);
    }

    public function testParsesFirstRating(): void
    {
        $rating = $this->parser->parse($this->html)[0];

        self::assertSame('atomic_rage', $rating->username);
        self::assertSame(7, $rating->rating);
    }
}
