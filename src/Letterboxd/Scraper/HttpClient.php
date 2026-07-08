<?php

declare(strict_types=1);

namespace App\Letterboxd\Scraper;

use App\Letterboxd\Exception\AuthenticationException;
use App\Letterboxd\Exception\HttpException;

interface HttpClient
{
    /**
     * Fetch a page relative to https://letterboxd.com/ and return its HTML body.
     *
     * @throws AuthenticationException when login fails
     * @throws HttpException           on any request error
     */
    public function get(string $path): string;
}
