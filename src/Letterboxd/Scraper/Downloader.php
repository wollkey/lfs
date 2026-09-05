<?php

declare(strict_types=1);

namespace App\Letterboxd\Scraper;

interface Downloader
{
    /**
     * Fetch an absolute URL and return its body, or null when the request failed.
     */
    public function get(string $url): ?string;
}
