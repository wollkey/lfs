<?php

declare(strict_types=1);

namespace App\Tests\Letterboxd;

use App\Letterboxd\PosterUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosterUrl::class)]
final class PosterUrlTest extends TestCase
{
    public function testResizesAFilmPosterUrl(): void
    {
        self::assertSame(
            'https://a.ltrbxd.com/resized/film-poster/2/7/0/2/2702-citizen-kane-0-1000-0-1500-crop.jpg?v=56bbc53dfd',
            PosterUrl::resized(
                'https://a.ltrbxd.com/resized/film-poster/2/7/0/2/2702-citizen-kane-0-250-0-375-crop.jpg?v=56bbc53dfd',
                1000,
                1500,
            ),
        );
    }

    public function testResizesAnUploadedPosterUrl(): void
    {
        self::assertSame(
            'https://a.ltrbxd.com/resized/sm/upload/6d/6l/r3/e9/hash.jpg-0-1000-0-1500-crop.jpg?v=58a476ae28',
            PosterUrl::resized(
                'https://a.ltrbxd.com/resized/sm/upload/6d/6l/r3/e9/hash.jpg-0-600-0-900-crop.jpg?v=58a476ae28',
                1000,
                1500,
            ),
        );
    }

    public function testLeavesAnUnsizedUrlAlone(): void
    {
        $url = 'https://a.ltrbxd.com/resized/sm/upload/jh/el/zf/hg/drive-2022-1200-1200-675-675-crop-000000.jpg';

        self::assertSame($url, PosterUrl::resized($url, 1000, 1500));
    }
}
