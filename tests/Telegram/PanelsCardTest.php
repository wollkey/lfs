<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Card\PanelsCard;
use App\Telegram\Cell;
use App\Telegram\Post;
use App\Telegram\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PanelsCard::class)]
final class PanelsCardTest extends TestCase
{
    public function testRendersTilesAndAwardsWithTitleAndIntro(): void
    {
        $post = new Post(
            'Круг 5 🏆 награды фильмам',
            new Table([], [
                [new Cell('Фильмов'), new Cell('10')],
                [new Cell('Лучший фильм'), new Cell('Stalker'), new Cell('8.6')],
            ]),
            intro: '18.05.2026 — н.в.',
        );

        $svg = new PanelsCard()->render($post);

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
        self::assertStringContainsString('КРУГ 5 НАГРАДЫ ФИЛЬМАМ', $svg);
        self::assertStringContainsString('18.05.2026 — н.в.', $svg);
        self::assertStringContainsString('ФИЛЬМОВ', $svg);
        self::assertStringContainsString('Stalker', $svg);
        self::assertStringContainsString('8.6', $svg);
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $post = new Post('Круг 1', new Table([], [
            [new Cell('Лучший фильм'), new Cell('Tom & Jerry <3'), new Cell('9.0')],
        ]));

        $svg = new PanelsCard()->render($post);

        self::assertStringContainsString('Tom &amp; Jerry &lt;3', $svg);
        self::assertStringNotContainsString('Tom & Jerry <3', $svg);
    }
}
