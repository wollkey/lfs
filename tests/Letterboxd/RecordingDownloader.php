<?php

declare(strict_types=1);

namespace App\Tests\Letterboxd;

use App\Letterboxd\Scraper\Downloader;

final class RecordingDownloader implements Downloader
{
    /**
     * @var list<string>
     */
    public array $requested = [];

    /**
     * @param array<string, ?string> $responses
     */
    public function __construct(
        private readonly array $responses,
    ) {
    }

    public function get(string $url): ?string
    {
        $this->requested[] = $url;

        foreach ($this->responses as $needle => $body) {
            if (str_contains($url, $needle)) {
                return $body;
            }
        }

        return null;
    }
}
