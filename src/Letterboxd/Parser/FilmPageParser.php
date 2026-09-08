<?php

declare(strict_types=1);

namespace App\Letterboxd\Parser;

use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Exception\NotFoundException;
use Symfony\Component\DomCrawler\Crawler;

final class FilmPageParser
{
    private const string JSON_LD = 'script[type="application/ld+json"]';
    private const string CDATA = '~^\s*/\*\s*<!\[CDATA\[\s*\*/\s*|\s*/\*\s*\]\]>\s*\*/\s*$~';
    private const string SLUG = '~/film/([^/]+)/~';

    /**
     * @throws NotFoundException
     */
    public function parse(string $html): ParsedFilm
    {
        $crawler = new Crawler($html);
        $movie = $this->jsonLd($crawler);

        $slug = $this->slug($crawler, $movie);
        $title = $this->title($crawler, $movie);

        if ($slug === null || $title === null) {
            throw new NotFoundException('Not a Letterboxd film page.');
        }

        return new ParsedFilm($slug, $title, $this->posterUrl($crawler, $movie));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(Crawler $crawler): array
    {
        $script = $crawler->filter(self::JSON_LD);
        if ($script->count() === 0) {
            return [];
        }

        // text() would collapse the whitespace inside the JSON strings.
        $json = preg_replace(self::CDATA, '', (string) $script->getNode(0)?->textContent);
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $movie
     */
    private function slug(Crawler $crawler, array $movie): ?string
    {
        $url = is_string($movie['url'] ?? null) ? $movie['url'] : $this->attr($crawler, 'link[rel="canonical"]', 'href');

        return $url !== null && preg_match(self::SLUG, $url, $match) === 1 ? $match[1] : null;
    }

    /**
     * @param array<string, mixed> $movie
     */
    private function title(Crawler $crawler, array $movie): ?string
    {
        $name = $movie['name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $title = $this->attr($crawler, 'meta[property="og:title"]', 'content');

        return $title !== null ? preg_replace('/\s+\(\d{4}\)$/', '', $title) : null;
    }

    /**
     * @param array<string, mixed> $movie
     */
    private function posterUrl(Crawler $crawler, array $movie): ?string
    {
        $image = $movie['image'] ?? null;
        if (is_string($image) && $image !== '') {
            return $image;
        }

        return $this->attr($crawler, 'meta[property="og:image"]', 'content');
    }

    private function attr(Crawler $crawler, string $selector, string $attribute): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $value = $node->first()->attr($attribute);

        return $value !== null && $value !== '' ? $value : null;
    }
}
