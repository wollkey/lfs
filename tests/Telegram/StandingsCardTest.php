<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Card\StandingsCard;
use App\Telegram\Post;
use App\Telegram\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandingsCard::class)]
final class StandingsCardTest extends TestCase
{
    public function testRendersFilmsTitleAndSubtitleAsSvg(): void
    {
        $post = new Post('Круг 5 🎬 итоги недели', new Table(
            ['#', 'Фильм', 'Балл', 'Голосов'],
            [['1', 'Stalker', '8.9', '8'], ['2', 'Drive', '7.8', '5']],
        ));

        $svg = new StandingsCard()->render($post);

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
        self::assertStringContainsString('Stalker', $svg);
        self::assertStringContainsString('Drive', $svg);
        self::assertStringContainsString('LAST FRAME SOCIETY', $svg);
        self::assertStringContainsString('КРУГ 5 ИТОГИ НЕДЕЛИ', $svg);
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $post = new Post('Круг 1', new Table(
            ['#', 'Фильм', 'Балл', 'Голосов'],
            [['1', 'Tom & Jerry <3', '9.0', '4']],
        ));

        $svg = new StandingsCard()->render($post);

        self::assertStringContainsString('Tom &amp; Jerry &lt;3', $svg);
        self::assertStringNotContainsString('Tom & Jerry <3', $svg);
    }

    public function testRendersOneClippedCellPerFilm(): void
    {
        $rows = [];
        for ($i = 1; $i <= 7; ++$i) {
            $rows[] = [(string) $i, 'Film '.$i, '7.0', '5'];
        }

        $svg = new StandingsCard()->render(new Post('Круг 2', new Table(['#', 'Фильм', 'Балл', 'Голосов'], $rows)));

        self::assertSame(7, substr_count($svg, 'clip-path="url'));
    }
}
