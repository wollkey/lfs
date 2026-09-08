<?php

declare(strict_types=1);

namespace App\Letterboxd\Parser;

use Symfony\Component\DomCrawler\Crawler;

final class ActivityParser
{
    private const string ROW = 'section.activity-row';
    private const string TARGET = 'p.activity-summary a.target';
    private const string RATING = 'p.activity-summary span.rating';

    /**
     * @return array<string, int>
     */
    public function parse(string $html): array
    {
        $ratings = [];

        foreach (new Crawler($html)->filter(self::ROW) as $node) {
            $row = new Crawler($node);

            $slug = $this->slug($row);
            $score = $this->score($row);

            // Reverse-chronological: the first occurrence is the newest rating.
            if ($slug !== null && $score !== null && !isset($ratings[$slug])) {
                $ratings[$slug] = $score;
            }
        }

        return $ratings;
    }

    private function slug(Crawler $row): ?string
    {
        $link = $row->filter(self::TARGET);
        if ($link->count() === 0) {
            return null;
        }

        $href = $link->attr('href') ?? '';

        return preg_match('~/film/([^/]+)/~', $href, $m) === 1 ? $m[1] : null;
    }

    private function score(Crawler $row): ?int
    {
        $node = $row->filter(self::RATING);
        if ($node->count() === 0) {
            return null;
        }

        $class = $node->attr('class') ?? '';

        if (preg_match('/\brated-(\d+)\b/', $class, $m) !== 1) {
            return null;
        }

        $score = (int) $m[1];

        return $score >= 1 && $score <= 10 ? $score : null;
    }
}
