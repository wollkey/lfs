<?php

declare(strict_types=1);

namespace App\Telegram\Card;

use App\Telegram\Cell;
use App\Telegram\Post;

final readonly class StandingsCard
{
    private const int ROWS_TOP = 272;
    private const int ROW_HEIGHT = 76;
    private const int ROW_STRIDE = 88;

    public function __construct(
        private CardChrome $chrome = new CardChrome(),
    ) {
    }

    public function render(Post $post): string
    {
        $rows = $post->table !== null ? $post->table->rows : [];
        $headers = $post->table !== null ? $post->table->headers : [];
        $height = self::ROWS_TOP + count($rows) * self::ROW_STRIDE + 64;

        $filmRight = CardChrome::WIDTH - CardChrome::MARGIN - 200;
        $scoreRight = CardChrome::WIDTH - CardChrome::MARGIN - 132;
        $votesRight = CardChrome::WIDTH - CardChrome::MARGIN - 24;

        $parts = [
            $this->chrome->open($height),
            $this->chrome->header($post->title),
            $this->chrome->text(CardChrome::MARGIN + 38, 240, 20, CardChrome::MUTED, 'middle', CardChrome::OSWALD, 600, $this->caption($headers, 0)),
            $this->chrome->text(CardChrome::MARGIN + 84, 240, 20, CardChrome::MUTED, 'start', CardChrome::OSWALD, 600, $this->caption($headers, 1), 'letter-spacing="1"'),
            $this->chrome->text($scoreRight, 240, 20, CardChrome::MUTED, 'end', CardChrome::OSWALD, 600, $this->caption($headers, 2), 'letter-spacing="1"'),
            $this->chrome->text($votesRight, 240, 20, CardChrome::MUTED, 'end', CardChrome::OSWALD, 600, $this->caption($headers, 3), 'letter-spacing="1"'),
            $this->chrome->rule(256),
        ];

        foreach ($rows as $index => $row) {
            $parts[] = $this->row($index, $row, $filmRight, $scoreRight, $votesRight);
        }

        $parts[] = $this->chrome->footer($height, 'LAST FRAME SOCIETY · РЕЙТИНГ КЛУБА');
        $parts[] = $this->chrome->close();

        return implode("\n", $parts);
    }

    /**
     * @param list<string> $headers
     */
    private function caption(array $headers, int $index): string
    {
        return mb_strtoupper($headers[$index] ?? '');
    }

    /**
     * @param list<Cell> $row
     */
    private function row(int $index, array $row, int $filmRight, int $scoreRight, int $votesRight): string
    {
        $rank = $row[0]->text;
        $film = $row[1]->text;
        $score = $row[2]->text;
        $votes = $row[3]->text;

        $top = self::ROWS_TOP + $index * self::ROW_STRIDE;
        $centerY = $top + self::ROW_HEIGHT / 2;
        $unrated = $score === '—';

        $badgeFill = match (true) {
            $unrated => '#202027',
            $index === 0 => CardChrome::ACCENT,
            default => CardChrome::BORDER,
        };
        $rankFill = match (true) {
            $unrated => CardChrome::DIM,
            $index === 0 => CardChrome::BG,
            default => CardChrome::TEXT,
        };
        $filmFill = $unrated ? CardChrome::DIM : CardChrome::TEXT;
        $scoreFill = $unrated ? CardChrome::DIM : CardChrome::ACCENT;
        $votesFill = $unrated ? CardChrome::DIM : CardChrome::MUTED;

        return implode('', [
            sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" rx="14" fill="%s" stroke="%s"/>',
                CardChrome::MARGIN,
                $top,
                CardChrome::WIDTH - 2 * CardChrome::MARGIN,
                self::ROW_HEIGHT,
                CardChrome::PANEL,
                CardChrome::BORDER,
            ),
            sprintf(
                '<rect x="%d" y="%d" width="44" height="44" rx="10" fill="%s"/>',
                CardChrome::MARGIN + 16,
                $centerY - 22,
                $badgeFill,
            ),
            $this->chrome->text(CardChrome::MARGIN + 38, $centerY + 11, 30, $rankFill, 'middle', CardChrome::BEBAS, 400, $rank),
            $this->clip(
                $this->chrome->text(CardChrome::MARGIN + 84, $centerY + 10, 30, $filmFill, 'start', CardChrome::OSWALD, 500, $film),
                CardChrome::MARGIN + 84,
                $top,
                $filmRight - (CardChrome::MARGIN + 84),
                self::ROW_HEIGHT,
                $index,
            ),
            $this->chrome->text($scoreRight, $centerY + 11, 32, $scoreFill, 'end', CardChrome::OSWALD, 600, $score),
            $this->chrome->text($votesRight, $centerY + 10, 26, $votesFill, 'end', CardChrome::OSWALD, 400, $votes),
        ]);
    }

    private function clip(string $inner, int $x, int $y, int $width, int $height, int $index): string
    {
        $id = 'film'.$index;

        return sprintf(
            '<clipPath id="%s"><rect x="%d" y="%d" width="%d" height="%d"/></clipPath>'
            .'<g clip-path="url(#%1$s)">%s</g>',
            $id,
            $x,
            $y,
            $width,
            $height,
            $inner,
        );
    }
}
