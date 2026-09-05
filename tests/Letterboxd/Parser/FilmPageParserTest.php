<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Exception\NotFoundException;
use App\Letterboxd\Parser\FilmPageParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilmPageParser::class)]
#[CoversClass(ParsedFilm::class)]
final class FilmPageParserTest extends TestCase
{
    private FilmPageParser $parser;
    private string $html;

    protected function setUp(): void
    {
        $this->parser = new FilmPageParser();
        $this->html = (string) file_get_contents(__DIR__.'/../Fixtures/film_page.html');
    }

    public function testReadsSlugTitleAndPosterFromJsonLd(): void
    {
        $film = $this->parser->parse($this->html);

        self::assertSame('drive-2011', $film->slug);
        self::assertSame('Drive', $film->title);
        self::assertSame(
            'https://a.ltrbxd.com/resized/sm/upload/6d/6l/r3/e9/nu7XIa67cXc2t7frXCE5voXUJcN.jpg-0-600-0-900-crop.jpg?v=58a476ae28',
            $film->posterUrl,
        );
    }

    public function testFallsBackToMetaTagsWithoutJsonLd(): void
    {
        $film = $this->parser->parse(<<<'HTML'
            <html><head>
            <link rel="canonical" href="https://letterboxd.com/film/stalker/">
            <meta property="og:title" content="Stalker (1979)">
            <meta property="og:image" content="https://a.ltrbxd.com/resized/film-poster/5/1/0/6/2/51062-stalker-0-600-0-900-crop.jpg">
            </head><body></body></html>
            HTML);

        self::assertSame('stalker', $film->slug);
        self::assertSame('Stalker', $film->title);
        self::assertStringContainsString('51062-stalker', (string) $film->posterUrl);
    }

    public function testMalformedJsonLdFallsBackInsteadOfThrowing(): void
    {
        $film = $this->parser->parse(<<<'HTML'
            <html><head>
            <script type="application/ld+json">/* <![CDATA[ */ {"name": broken, }} /* ]]> */</script>
            <link rel="canonical" href="https://letterboxd.com/film/stalker/">
            <meta property="og:title" content="Stalker (1979)">
            </head><body></body></html>
            HTML);

        self::assertSame('stalker', $film->slug);
        self::assertSame('Stalker', $film->title);
        self::assertNull($film->posterUrl);
    }

    public function testRejectsAPageThatIsNotAFilm(): void
    {
        $this->expectException(NotFoundException::class);

        $this->parser->parse('<html><head><title>Letterboxd</title></head><body></body></html>');
    }
}
