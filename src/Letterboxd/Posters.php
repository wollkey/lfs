<?php

declare(strict_types=1);

namespace App\Letterboxd;

use App\Letterboxd\Scraper\Downloader;

/**
 * The poster files served from public/posters, one JPEG per film slug.
 */
final readonly class Posters
{
    private const int WIDTH = 1000;
    private const int HEIGHT = 1500;
    private const string JPEG = "\xFF\xD8";
    private const int MIN_BYTES = 1024;

    public function __construct(
        private Downloader $downloader,
        private string $dir,
    ) {
    }

    public function has(string $slug): bool
    {
        return is_file($this->path($slug));
    }

    /**
     * Downloads the poster at full resolution. Returns false when nothing was written.
     */
    public function fetch(string $slug, string $url): bool
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0o775, true) && !is_dir($this->dir)) {
            return false;
        }

        $bytes = $this->downloader->get(PosterUrl::resized($url, self::WIDTH, self::HEIGHT));

        if ($bytes === null || strlen($bytes) < self::MIN_BYTES || !str_starts_with($bytes, self::JPEG)) {
            return false;
        }

        // Write aside and swap: a truncated poster would be synced to production as is.
        $target = $this->path($slug);
        $temp = $target.'.tmp';

        return file_put_contents($temp, $bytes) !== false && rename($temp, $target);
    }

    private function path(string $slug): string
    {
        return "{$this->dir}/{$slug}.jpg";
    }
}
