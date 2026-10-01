<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Card\SpotlightCard;
use App\Telegram\Cell;
use App\Telegram\Post;
use App\Telegram\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpotlightCard::class)]
final class SpotlightCardTest extends TestCase
{
    public function testRendersTheNameAndTheFilmOfTheWeek(): void
    {
        $svg = new SpotlightCard()->render($this->post('Алина Ф.', 'The Day After Tomorrow'));

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('НЕДЕЛЯ СЛАВЫ', $svg);
        self::assertStringContainsString('АЛИНА Ф.', $svg);
        self::assertStringContainsString('ВЫБОР НЕДЕЛИ', $svg);
        self::assertStringContainsString('The Day After Tomorrow', $svg);
    }

    public function testShrinksALongNameToFitTheCard(): void
    {
        $short = new SpotlightCard()->render($this->post('Лена', 'Stalker'));
        $long = new SpotlightCard()->render($this->post('Константин Константинопольский', 'Stalker'));

        self::assertStringContainsString('font-size="150"', $short);
        self::assertStringNotContainsString('font-size="150"', $long);
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $svg = new SpotlightCard()->render($this->post('Лена', 'Tom & Jerry <3'));

        self::assertStringContainsString('Tom &amp; Jerry &lt;3', $svg);
    }

    private function post(string $name, string $film): Post
    {
        return new Post('⭐ Неделя славы', new Table([], [[new Cell($name), new Cell($film)]]));
    }
}
