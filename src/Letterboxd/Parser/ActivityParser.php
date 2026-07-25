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
     * Ratings from an activity-pagination fragment
     * (/ajax/activity-pagination/{username}/). The member is not identifiable
     * from the markup — a friend's rows carry their name but the logged-in
     * member's own rows read "You rated" — so the caller supplies the username
     * from the file name. Non-rating events (followed, watched, liked, reviewed
     * without a score) are skipped. Activity is reverse-chronological, so the
     * first occurrence of a slug is the most recent rating and wins.
     *
     * @return array<string, int> score keyed by film slug
     */
    public function parse(string $html): array
    {
        $ratings = [];

        foreach (new Crawler($html)->filter(self::ROW) as $node) {
            $row = new Crawler($node);

            $slug = $this->slug($row);
            $score = $this->score($row);

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
