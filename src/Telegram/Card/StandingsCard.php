<?php

declare(strict_types=1);

namespace App\Telegram\Card;

use App\Telegram\Cell;
use App\Telegram\Post;

/**
 * Renders the weekly standings as an SVG poster in the "Bridge" style:
 * the site's dark, single-accent discipline with a filmstrip/reel motif.
 */
final readonly class StandingsCard
{
    public const int WIDTH = 1080;
    private const int MARGIN = 56;
    private const int ROWS_TOP = 272;
    private const int ROW_HEIGHT = 76;
    private const int ROW_STRIDE = 88;

    private const string BG = '#0e0e10';
    private const string PANEL = '#17171b';
    private const string BORDER = '#26262c';
    private const string ACCENT = '#e0a04d';
    private const string TEXT = '#e8e8ea';
    private const string MUTED = '#8a8a92';
    private const string DIM = '#5c5c63';
    private const string BEBAS = 'Bebas Neue';
    private const string OSWALD = 'Oswald';

    public function render(Post $post): string
    {
        $rows = $post->table !== null ? $post->table->rows : [];
        $height = self::ROWS_TOP + count($rows) * self::ROW_STRIDE + 64;
        $filmRight = self::WIDTH - self::MARGIN - 200;
        $scoreRight = self::WIDTH - self::MARGIN - 132;
        $votesRight = self::WIDTH - self::MARGIN - 24;

        $parts = [
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %1$d %2$d">',
                self::WIDTH,
                $height,
            ),
            $this->defs(),
            sprintf('<rect width="%d" height="%d" fill="%s"/>', self::WIDTH, $height, self::BG),
            sprintf('<rect width="%d" height="%d" fill="url(#glow)"/>', self::WIDTH, $height),
            $this->filmstrip(),
            $this->reel(self::MARGIN + 48, 108),
            $this->reel(self::WIDTH - self::MARGIN - 48, 108),
            $this->text(self::WIDTH / 2, 128, 68, 'url(#title)', 'middle', self::BEBAS, 400, 'LAST FRAME SOCIETY'),
            $this->text(self::WIDTH / 2, 170, 24, self::MUTED, 'middle', self::OSWALD, 600, $this->subtitle($post->title), 'letter-spacing="3"'),
            $this->rule(200),
            $this->text(self::MARGIN + 38, 240, 20, self::MUTED, 'middle', self::OSWALD, 600, '№'),
            $this->text(self::MARGIN + 84, 240, 20, self::MUTED, 'start', self::OSWALD, 600, 'ФИЛЬМ', 'letter-spacing="1"'),
            $this->text($scoreRight, 240, 20, self::MUTED, 'end', self::OSWALD, 600, 'БАЛЛ', 'letter-spacing="1"'),
            $this->text($votesRight, 240, 20, self::MUTED, 'end', self::OSWALD, 600, 'ГОЛОСА', 'letter-spacing="1"'),
            $this->rule(256),
        ];

        foreach ($rows as $index => $row) {
            $parts[] = $this->row($index, $row, $filmRight, $scoreRight, $votesRight);
        }

        $parts[] = $this->text(self::WIDTH / 2, $height - 26, 20, self::DIM, 'middle', self::OSWALD, 400, 'LAST FRAME SOCIETY · РЕЙТИНГ КЛУБА', 'letter-spacing="2"');
        $parts[] = '</svg>';

        return implode("\n", $parts);
    }

    /**
     * @param list<Cell> $row rank, film, score, votes
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
            $index === 0 => self::ACCENT,
            default => self::BORDER,
        };
        $rankFill = match (true) {
            $unrated => self::DIM,
            $index === 0 => self::BG,
            default => self::TEXT,
        };
        $filmFill = $unrated ? self::DIM : self::TEXT;
        $scoreFill = $unrated ? self::DIM : self::ACCENT;
        $votesFill = $unrated ? self::DIM : self::MUTED;

        return implode('', [
            sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" rx="14" fill="%s" stroke="%s"/>',
                self::MARGIN,
                $top,
                self::WIDTH - 2 * self::MARGIN,
                self::ROW_HEIGHT,
                self::PANEL,
                self::BORDER,
            ),
            sprintf(
                '<rect x="%d" y="%d" width="44" height="44" rx="10" fill="%s"/>',
                self::MARGIN + 16,
                $centerY - 22,
                $badgeFill,
            ),
            $this->text(self::MARGIN + 38, $centerY + 11, 30, $rankFill, 'middle', self::BEBAS, 400, $rank),
            $this->clip(
                $this->text(self::MARGIN + 84, $centerY + 10, 30, $filmFill, 'start', self::OSWALD, 500, $film),
                self::MARGIN + 84,
                $top,
                $filmRight - (self::MARGIN + 84),
                self::ROW_HEIGHT,
                $index,
            ),
            $this->text($scoreRight, $centerY + 11, 32, $scoreFill, 'end', self::OSWALD, 600, $score),
            $this->text($votesRight, $centerY + 10, 26, $votesFill, 'end', self::OSWALD, 400, $votes),
        ]);
    }

    private function defs(): string
    {
        return '<defs>'
            .'<linearGradient id="title" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0" stop-color="#f2d3a0"/><stop offset="1" stop-color="'.self::ACCENT.'"/>'
            .'</linearGradient>'
            .'<radialGradient id="glow" cx="0.85" cy="0.12" r="0.5">'
            .'<stop offset="0" stop-color="'.self::ACCENT.'" stop-opacity="0.12"/>'
            .'<stop offset="1" stop-color="'.self::ACCENT.'" stop-opacity="0"/>'
            .'</radialGradient>'
            .'</defs>';
    }

    private function filmstrip(): string
    {
        $holes = '';
        for ($x = self::MARGIN; $x <= self::WIDTH - self::MARGIN - 22; $x += 54) {
            $holes .= sprintf('<rect x="%d" y="14" width="22" height="10" rx="2"/>', $x);
            $holes .= sprintf('<rect x="%d" y="34" width="22" height="10" rx="2"/>', $x);
        }

        return sprintf('<rect width="%d" height="58" fill="%s"/>', self::WIDTH, self::PANEL)
            .sprintf('<g fill="%s" opacity="0.5">%s</g>', self::ACCENT, $holes);
    }

    private function reel(int $cx, int $cy): string
    {
        return sprintf(
            '<g transform="translate(%d %d)" fill="none" stroke="%s" stroke-width="2">'
            .'<circle r="22"/><circle r="5" fill="%3$s" stroke="none"/>'
            .'<circle cx="0" cy="-12" r="3.5" fill="%3$s" stroke="none"/>'
            .'<circle cx="10" cy="6" r="3.5" fill="%3$s" stroke="none"/>'
            .'<circle cx="-10" cy="6" r="3.5" fill="%3$s" stroke="none"/>'
            .'</g>',
            $cx,
            $cy,
            self::ACCENT,
        );
    }

    private function rule(int $y): string
    {
        return sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%2$d" stroke="%s" stroke-width="1" opacity="0.4"/>',
            self::MARGIN,
            $y,
            self::WIDTH - self::MARGIN,
            self::ACCENT,
        );
    }

    private function text(
        int|float $x,
        int|float $y,
        int $size,
        string $fill,
        string $anchor,
        string $family,
        int $weight,
        string $content,
        string $extra = '',
    ): string {
        return sprintf(
            '<text x="%s" y="%s" font-size="%d" fill="%s" text-anchor="%s" font-family="%s" font-weight="%d"%s>%s</text>',
            $this->num($x),
            $this->num($y),
            $size,
            $fill,
            $anchor,
            $family,
            $weight,
            $extra === '' ? '' : ' '.$extra,
            $this->escape($content),
        );
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

    private function subtitle(string $title): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s·]/u', '', $title) ?? $title;
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return mb_strtoupper($clean);
    }

    private function num(int|float $value): string
    {
        return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.');
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
