<?php

declare(strict_types=1);

namespace App\Letterboxd\Scraper;

interface Downloader
{
    public function get(string $url): ?string;
}
