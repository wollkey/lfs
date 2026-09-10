<?php

declare(strict_types=1);

namespace App\Tests\Letterboxd;

use App\Letterboxd\Posters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Posters::class)]
final class PostersTest extends TestCase
{
    private const string URL = 'https://a.ltrbxd.com/resized/sm/upload/poster.jpg-0-600-0-900-crop.jpg';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/lfs-posters-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,*/}*.jpg', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $subdir) {
            rmdir($subdir);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testFetchStoresTheOriginalAndTheSizesTheSiteRenders(): void
    {
        $downloader = new RecordingDownloader(['a.ltrbxd.com' => Jpeg::bytes()]);

        self::assertTrue(new Posters($downloader, $this->dir)->fetch('drive-2011', self::URL));

        self::assertSame([1000, 1500], $this->dimensions('drive-2011.jpg'));
        self::assertSame([500, 750], $this->dimensions('w500/drive-2011.jpg'));
        self::assertSame([300, 450], $this->dimensions('w300/drive-2011.jpg'));
    }

    public function testResizeDerivesMissingSizesFromDiskWithoutDownloadingAnything(): void
    {
        mkdir($this->dir, 0o775, true);
        file_put_contents($this->dir.'/stalker.jpg', Jpeg::bytes());

        $downloader = new RecordingDownloader([]);
        $posters = new Posters($downloader, $this->dir);

        self::assertTrue($posters->has('stalker'));
        self::assertTrue($posters->resize('stalker'));

        self::assertSame([500, 750], $this->dimensions('w500/stalker.jpg'));
        self::assertSame([300, 450], $this->dimensions('w300/stalker.jpg'));
        self::assertSame([], $downloader->requested);
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $file): array
    {
        $size = getimagesize($this->dir.'/'.$file);
        self::assertNotFalse($size, "Not an image: {$file}");

        return [$size[0], $size[1]];
    }
}
