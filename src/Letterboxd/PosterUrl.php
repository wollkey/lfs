<?php

declare(strict_types=1);

namespace App\Letterboxd;

final readonly class PosterUrl
{
    private const string SIZE = '/-0-\d+-0-\d+-crop\.jpg/';

    public static function resized(string $url, int $width, int $height): string
    {
        return preg_replace(self::SIZE, "-0-{$width}-0-{$height}-crop.jpg", $url) ?? $url;
    }
}
