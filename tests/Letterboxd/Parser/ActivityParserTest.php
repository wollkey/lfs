<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Letterboxd\Parser\ActivityParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityParser::class)]
final class ActivityParserTest extends TestCase
{
    private ActivityParser $parser;
    private string $html;

    protected function setUp(): void
    {
        $this->parser = new ActivityParser();
        $this->html = (string) file_get_contents(__DIR__.'/../Fixtures/activity_pagination.html');
    }

    public function testParsesEveryRatedFilm(): void
    {
        $ratings = $this->parser->parse($this->html);

        self::assertCount(6, $ratings);
    }

    public function testKeysScoreBySlug(): void
    {
        $ratings = $this->parser->parse($this->html);

        self::assertArrayHasKey('drive-2011', $ratings);
        self::assertSame(6, $ratings['drive-2011']);
    }

    public function testParsesFractionalStarScores(): void
    {
        $ratings = $this->parser->parse($this->html);

        self::assertSame(7, $ratings['stalker']);
        self::assertSame(4, $ratings['taxi-driver']);
    }

    /**
     * The logged-in member's own fragment says "You rated" (no name link) and
     * uses relative film hrefs. The slug and score must still be extracted.
     */
    public function testParsesOwnRowsWithoutNameLink(): void
    {
        $html = <<<'HTML'
            <html><body>
            <section class="activity-row -basic"> <div class="table-activity-description"> <p class="activity-summary"> You rated <a href="/film/goodfellas/" class="target">GoodFellas</a> <span class="rating -tiny rated-7"> ★★★½ </span> </p> </div> </section>
            </body></html>
            HTML;

        self::assertSame(['goodfellas' => 7], $this->parser->parse($html));
    }

    public function testReturnsEmptyForFragmentWithoutRows(): void
    {
        self::assertSame([], $this->parser->parse('<html><body></body></html>'));
    }
}
