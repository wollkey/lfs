<?php

declare(strict_types=1);

namespace App\Letterboxd;

/**
 * Letterboxd's CDN encodes the requested size in the file name and generates
 * any size on demand, so a bigger poster is a rewrite rather than a new request.
 */
final readonly class PosterUrl
{
    private const string SIZE = '/-0-\d+-0-\d+-crop\.jpg/';

    public static function resized(string $url, int $width, int $height): string
    {
        return preg_replace(self::SIZE, "-0-{$width}-0-{$height}-crop.jpg", $url) ?? $url;
    }
}
