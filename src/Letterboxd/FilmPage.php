<?php

declare(strict_types=1);

namespace App\Letterboxd;

use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Exception\LetterboxdException;
use App\Letterboxd\Parser\FilmPageParser;
use App\Letterboxd\Scraper\Downloader;

/**
 * The public film page — readable without a session, unlike the friends and
 * activity pages, so titles and posters need no browser.
 */
final readonly class FilmPage
{
    private const string URL = 'https://letterboxd.com/film/%s/';

    public function __construct(
        private Downloader $downloader,
        private FilmPageParser $parser,
    ) {
    }

    public function fetch(string $slug): ?ParsedFilm
    {
        $html = $this->downloader->get(sprintf(self::URL, $slug));
        if ($html === null) {
            return null;
        }

        try {
            return $this->parser->parse($html);
        } catch (LetterboxdException) {
            return null;
        }
    }
}
