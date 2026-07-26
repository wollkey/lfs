<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\RenderException;

interface Rasterizer
{
    /**
     * Rasterize an SVG document to a PNG file and return its path.
     * The caller owns the returned file and should delete it.
     *
     * @throws RenderException
     */
    public function toPng(string $svg): string;
}
