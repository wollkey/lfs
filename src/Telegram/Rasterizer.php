<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\RenderException;

interface Rasterizer
{
    /**
     * @throws RenderException
     */
    public function toPng(string $svg): string;
}
