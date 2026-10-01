<?php

declare(strict_types=1);

namespace App\Telegram\Card;

use App\Telegram\Post;

final readonly class SpotlightCard
{
    private const int HEIGHT = 640;
    private const int TEXT_WIDTH = CardChrome::WIDTH - 2 * CardChrome::MARGIN;

    public function __construct(
        private CardChrome $chrome = new CardChrome(),
    ) {
    }

    public function render(Post $post): string
    {
        $row = $post->table !== null ? ($post->table->rows[0] ?? []) : [];
        $name = mb_strtoupper($row[0]->text ?? '');
        $film = $row[1]->text ?? '';

        return implode("\n", [
            $this->chrome->open(self::HEIGHT),
            $this->chrome->header($post->title),
            $this->chrome->text(CardChrome::WIDTH / 2, 410, $this->fit($name, 150, 0.62), 'url(#title)', 'middle', CardChrome::OSWALD, 600, $name, 'letter-spacing="4"'),
            $this->chrome->rule(470),
            $this->chrome->text(CardChrome::WIDTH / 2, 518, 20, CardChrome::MUTED, 'middle', CardChrome::OSWALD, 600, 'ВЫБОР НЕДЕЛИ', 'letter-spacing="3"'),
            $this->chrome->text(CardChrome::WIDTH / 2, 566, $this->fit($film, 38, 0.5), CardChrome::TEXT, 'middle', CardChrome::OSWALD, 500, $film),
            $this->chrome->footer(self::HEIGHT, 'LAST FRAME SOCIETY · НЕДЕЛЯ СЛАВЫ'),
            $this->chrome->close(),
        ]);
    }

    private function fit(string $text, int $size, float $glyphWidth): int
    {
        $length = max(1, mb_strlen($text));

        return (int) min($size, floor(self::TEXT_WIDTH / ($length * $glyphWidth)));
    }
}
