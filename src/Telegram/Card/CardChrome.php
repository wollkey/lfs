<?php

declare(strict_types=1);

namespace App\Telegram\Card;

final readonly class CardChrome
{
    public const int WIDTH = 1080;
    public const int MARGIN = 56;

    public const string BG = '#0e0e10';
    public const string PANEL = '#17171b';
    public const string BORDER = '#26262c';
    public const string ACCENT = '#e0a04d';
    public const string TEXT = '#e8e8ea';
    public const string MUTED = '#8a8a92';
    public const string DIM = '#5c5c63';
    public const string BEBAS = 'Bebas Neue';
    public const string OSWALD = 'Oswald';

    public function __construct(
        private string $accent = self::ACCENT,
    ) {
    }

    public function open(int $height): string
    {
        return implode("\n", [
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %1$d %2$d">',
                self::WIDTH,
                $height,
            ),
            $this->defs(),
            sprintf('<rect width="%d" height="%d" fill="%s"/>', self::WIDTH, $height, self::BG),
            sprintf('<rect width="%d" height="%d" fill="url(#glow)"/>', self::WIDTH, $height),
        ]);
    }

    public function header(string $title): string
    {
        return implode("\n", [
            $this->filmstrip(),
            $this->reel(self::MARGIN + 48, 108),
            $this->reel(self::WIDTH - self::MARGIN - 48, 108),
            $this->text(self::WIDTH / 2, 128, 68, 'url(#title)', 'middle', self::BEBAS, 400, 'LAST FRAME SOCIETY'),
            $this->text(self::WIDTH / 2, 170, 24, self::MUTED, 'middle', self::OSWALD, 600, $this->subtitle($title), 'letter-spacing="3"'),
            $this->rule(200),
        ]);
    }

    public function footer(int $height, string $tagline): string
    {
        return $this->text(self::WIDTH / 2, $height - 26, 20, self::DIM, 'middle', self::OSWALD, 400, $tagline, 'letter-spacing="2"');
    }

    public function close(): string
    {
        return '</svg>';
    }

    public function text(
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

    public function rule(int $y): string
    {
        return sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%2$d" stroke="%s" stroke-width="1" opacity="0.4"/>',
            self::MARGIN,
            $y,
            self::WIDTH - self::MARGIN,
            self::ACCENT,
        );
    }

    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function defs(): string
    {
        return '<defs>'
            .'<linearGradient id="title" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0" stop-color="#f2d3a0"/><stop offset="1" stop-color="'.self::ACCENT.'"/>'
            .'</linearGradient>'
            .'<radialGradient id="glow" cx="0.85" cy="0.12" r="0.5">'
            .'<stop offset="0" stop-color="'.$this->accent.'" stop-opacity="0.12"/>'
            .'<stop offset="1" stop-color="'.$this->accent.'" stop-opacity="0"/>'
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
}
