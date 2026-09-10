<?php

declare(strict_types=1);

namespace App\Tests\Letterboxd;

final readonly class Jpeg
{
    public static function bytes(int $width = 1000, int $height = 1500): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
