<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\RenderException;

final readonly class RsvgRasterizer implements Rasterizer
{
    public function __construct(
        private string $binary = 'rsvg-convert',
        private int $width = 1080,
    ) {
    }

    public function toPng(string $svg): string
    {
        $png = tempnam(sys_get_temp_dir(), 'lfs_card_');
        if ($png === false) {
            throw new RenderException('Cannot create a temporary file.');
        }

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            [$this->binary, '--format=png', '--width='.$this->width, '--keep-aspect-ratio', '--output='.$png],
            $descriptors,
            $pipes,
        );

        if (!is_resource($process)) {
            @unlink($png);

            throw new RenderException('Cannot start '.$this->binary.'.');
        }

        fwrite($pipes[0], $svg);
        fclose($pipes[0]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        clearstatcache(true, $png);
        if ($exit !== 0 || filesize($png) < 100) {
            @unlink($png);

            throw new RenderException(sprintf('%s failed (exit %d): %s', $this->binary, $exit, trim($stderr)));
        }

        return $png;
    }
}
