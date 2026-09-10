<?php

declare(strict_types=1);

namespace App\Letterboxd;

use App\Letterboxd\Scraper\Downloader;

final readonly class Posters
{
    private const int WIDTH = 1000;
    private const int HEIGHT = 1500;
    private const string JPEG = "\xFF\xD8";
    private const int MIN_BYTES = 1024;

    /**
     * Derivatives for the site. The 1000px original stays at the root: the Telegram cards need it.
     */
    private const array SIZES = ['w500' => 500, 'w300' => 300];
    private const int QUALITY = 75;

    public function __construct(
        private Downloader $downloader,
        private string $dir,
    ) {
    }

    public function has(string $slug): bool
    {
        return is_file($this->path($slug));
    }

    public function fetch(string $slug, string $url): bool
    {
        if (!$this->makeDir($this->dir)) {
            return false;
        }

        $bytes = $this->downloader->get(PosterUrl::resized($url, self::WIDTH, self::HEIGHT));

        if ($bytes === null || strlen($bytes) < self::MIN_BYTES || !str_starts_with($bytes, self::JPEG)) {
            return false;
        }

        if (!$this->write($this->path($slug), $bytes)) {
            return false;
        }

        return $this->resize($slug, force: true);
    }

    public function resize(string $slug, bool $force = false): bool
    {
        $source = null;

        foreach (self::SIZES as $size => $width) {
            $target = $this->path($slug, $size);
            if (!$force && is_file($target)) {
                continue;
            }

            $source ??= @imagecreatefromjpeg($this->path($slug));
            if ($source === false) {
                return false;
            }

            $small = imagescale($source, $width, mode: IMG_BICUBIC);
            if ($small === false || !$this->makeDir(dirname($target))) {
                return false;
            }

            imageinterlace($small, true);

            $temp = $target.'.tmp';
            if (!imagejpeg($small, $temp, self::QUALITY) || !rename($temp, $target)) {
                return false;
            }
        }

        return true;
    }

    private function makeDir(string $dir): bool
    {
        return is_dir($dir) || mkdir($dir, 0o775, true) || is_dir($dir);
    }

    private function write(string $target, string $bytes): bool
    {
        $temp = $target.'.tmp';

        return file_put_contents($temp, $bytes) !== false && rename($temp, $target);
    }

    private function path(string $slug, string $size = ''): string
    {
        return $size === '' ? "{$this->dir}/{$slug}.jpg" : "{$this->dir}/{$size}/{$slug}.jpg";
    }
}
