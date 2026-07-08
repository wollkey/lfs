<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Parser\ListParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListParser::class)]
#[CoversClass(ParsedFilm::class)]
final class ListParserTest extends TestCase
{
    private ListParser $parser;
    private string $html;

    protected function setUp(): void
    {
        $this->parser = new ListParser();
        $this->html = (string) file_get_contents(__DIR__.'/../Fixtures/list.html');
    }

    public function testParsesEveryFilm(): void
    {
        $films = $this->parser->parse($this->html);

        self::assertCount(56, $films);
    }

    public function testParsesFirstFilm(): void
    {
        $film = $this->parser->parse($this->html)[0];

        self::assertSame('citizen-kane', $film->slug);
        self::assertSame('Citizen Kane', $film->title);
    }
}
