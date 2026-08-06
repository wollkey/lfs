<?php

declare(strict_types=1);

namespace App\Telegram\Card;

use App\Telegram\Cell;
use App\Telegram\Post;

final readonly class PanelsCard
{
    private const int PANEL_HEIGHT = 104;
    private const int PANEL_STRIDE = 120;

    public function __construct(
        private CardChrome $chrome = new CardChrome(),
    ) {
    }

    public function render(Post $post, string $tagline = 'LAST FRAME SOCIETY · ИТОГИ КРУГА'): string
    {
        $rows = $post->table !== null ? $post->table->rows : [];
        $top = $post->intro !== null ? 300 : 256;
        $height = $top + count($rows) * self::PANEL_STRIDE + 48;

        $parts = [
            $this->chrome->open($height),
            $this->chrome->header($post->title),
        ];

        if ($post->intro !== null) {
            $parts[] = $this->chrome->text(CardChrome::WIDTH / 2, 244, 26, CardChrome::MUTED, 'middle', CardChrome::OSWALD, 400, $post->intro, 'letter-spacing="1"');
        }

        foreach ($rows as $index => $row) {
            $parts[] = $this->panel($index, $row, $top);
        }

        $parts[] = $this->chrome->footer($height, $tagline);
        $parts[] = $this->chrome->close();

        return implode("\n", $parts);
    }

    /**
     * @param list<Cell> $row
     */
    private function panel(int $index, array $row, int $top): string
    {
        $y = $top + $index * self::PANEL_STRIDE;
        $left = CardChrome::MARGIN + 32;

        $frame = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="16" fill="%s" stroke="%s"/>',
            CardChrome::MARGIN,
            $y,
            CardChrome::WIDTH - 2 * CardChrome::MARGIN,
            self::PANEL_HEIGHT,
            CardChrome::PANEL,
            CardChrome::BORDER,
        );

        $label = $this->chrome->text($left, $y + 36, 20, CardChrome::MUTED, 'start', CardChrome::OSWALD, 600, mb_strtoupper($row[0]->text), 'letter-spacing="1"');

        if (count($row) >= 3) {
            return $frame.$label.$this->award($row, $index, $y, $left);
        }

        return $frame.$label.$this->tile($row, $y, $left);
    }

    /**
     * @param list<Cell> $row
     */
    private function award(array $row, int $index, int $y, int $left): string
    {
        $valueLeft = CardChrome::WIDTH - CardChrome::MARGIN - 32;
        $value = $row[2]->text;

        $primary = $this->clip(
            $this->chrome->text($left, $y + 78, 34, CardChrome::TEXT, 'start', CardChrome::OSWALD, 500, $row[1]->text),
            $left,
            $y,
            CardChrome::WIDTH - CardChrome::MARGIN - 32 - 240 - $left,
            self::PANEL_HEIGHT,
            $index,
        );

        if ($value === '') {
            return $primary;
        }

        return $primary.$this->chrome->text($valueLeft, $y + 74, 42, CardChrome::ACCENT, 'end', CardChrome::OSWALD, 600, $value);
    }

    /**
     * @param list<Cell> $row
     */
    private function tile(array $row, int $y, int $left): string
    {
        return $this->chrome->text($left, $y + 84, 52, CardChrome::ACCENT, 'start', CardChrome::OSWALD, 600, $row[1]->text);
    }

    private function clip(string $inner, int $x, int $y, int $width, int $height, int $index): string
    {
        $id = 'panel'.$index;

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
