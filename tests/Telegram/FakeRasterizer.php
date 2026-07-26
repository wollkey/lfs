<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Exception\RenderException;
use App\Telegram\Rasterizer;

final readonly class FakeRasterizer implements Rasterizer
{
    public function __construct(
        private bool $fail = false,
    ) {
    }

    public function toPng(string $svg): string
    {
        if ($this->fail) {
            throw new RenderException('simulated render failure');
        }

        $path = tempnam(sys_get_temp_dir(), 'lfs_test_');
        if ($path === false) {
            throw new RenderException('cannot create temp file');
        }

        file_put_contents($path, 'PNG');

        return $path;
    }
}
