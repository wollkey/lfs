<?php

declare(strict_types=1);

namespace App\Letterboxd\Parser;

use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Dto\ParsedRating;
use Symfony\Component\DomCrawler\Crawler;

final class ListParser
{
    private const string ITEM = 'ul.poster-list li.posteritem';
    private const string POSTER = 'div.react-component';
    private const string OWNER = 'body.list-page';

    /**
     * Films in the list (slug + title only).
     *
     * @return list<ParsedFilm>
     */
    public function parse(string $html): array
    {
        $films = new Crawler($html)
            ->filter(self::ITEM)
            ->each(fn (Crawler $li): ?ParsedFilm => $this->film($li));

        return array_values(array_filter($films));
    }

    /**
     * The list owner's own ratings, one ParsedRating per rated film.
     * The owner is the same kind of member as everyone else; their
     * rating just happens to live on the list page rather than the
     * friends page.
     *
     * @return array<string, ParsedRating> keyed by film slug
     */
    public function ownerRatings(string $html): array
    {
        $crawler = new Crawler($html);

        $owner = $this->owner($crawler);
        if ($owner === null) {
            return [];
        }

        $ratings = [];

        foreach ($crawler->filter(self::ITEM) as $node) {
            $li = new Crawler($node);

            $slug = $this->slug($li);
            $rating = $this->intOrNull($li->attr('data-owner-rating'));

            if ($slug !== null && $rating !== null && $rating > 0) {
                $ratings[$slug] = new ParsedRating($owner, $rating);
            }
        }

        return $ratings;
    }

    private function film(Crawler $li): ?ParsedFilm
    {
        $slug = $this->slug($li);
        if ($slug === null) {
            return null;
        }

        $poster = $li->filter(self::POSTER);
        $title = $poster->count() > 0 ? ($poster->attr('data-item-name') ?? $slug) : $slug;

        return new ParsedFilm($slug, $this->cleanTitle($title));
    }

    private function slug(Crawler $li): ?string
    {
        $poster = $li->filter(self::POSTER);
        if ($poster->count() === 0) {
            return null;
        }

        $slug = $poster->attr('data-item-slug');

        return $slug !== null && $slug !== '' ? $slug : null;
    }

    private function owner(Crawler $crawler): ?string
    {
        $body = $crawler->filter(self::OWNER);
        if ($body->count() === 0) {
            return null;
        }

        $owner = $body->attr('data-owner');

        return $owner !== null && $owner !== '' ? $owner : null;
    }

    private function cleanTitle(string $itemName): string
    {
        return preg_replace('/\s+\(\d{4}\)$/', '', $itemName) ?? $itemName;
    }

    private function intOrNull(?string $value): ?int
    {
        return $value !== null && $value !== '' ? (int) $value : null;
    }
}
