<?php

declare(strict_types=1);

namespace App\Letterboxd\Scraper;

final readonly class StreamDownloader implements Downloader
{
    private const string USER_AGENT = 'LFS poster fetcher (personal film club project)';

    public function __construct(
        private int $timeout = 15,
    ) {
    }

    public function get(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: '.self::USER_AGENT,
                'timeout' => $this->timeout,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        return $data === false ? null : $data;
    }
}
