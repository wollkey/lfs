<?php

declare(strict_types=1);

namespace App\Letterboxd\Parser;

use App\Letterboxd\Dto\ParsedRating;
use Symfony\Component\DomCrawler\Crawler;

final class FriendsRatingsParser
{
    private const string ROW = 'table.member-table tbody tr';
    private const string NAME_LINK = 'td.col-member a.name';
    private const string RATING = 'td.col-rating span.rating';

    /**
     * Friends' ratings for one film. Rows without a rating (watched but
     * not rated) are skipped.
     *
     * @return list<ParsedRating>
     */
    public function parse(string $html): array
    {
        $ratings = new Crawler($html)
            ->filter(self::ROW)
            ->each(fn (Crawler $tr): ?ParsedRating => $this->rating($tr));

        return array_values(array_filter($ratings));
    }

    private function rating(Crawler $tr): ?ParsedRating
    {
        $username = $this->username($tr);
        $score = $this->score($tr);

        return $username !== null && $score !== null
            ? new ParsedRating($username, $score)
            : null;
    }

    private function username(Crawler $tr): ?string
    {
        $link = $tr->filter(self::NAME_LINK);
        if ($link->count() === 0) {
            return null;
        }

        $href = $link->attr('href') ?? '';

        return trim($href, '/') ?: null;
    }

    private function score(Crawler $tr): ?int
    {
        $node = $tr->filter(self::RATING);
        if ($node->count() === 0) {
            return null;
        }

        $class = $node->attr('class') ?? '';

        return preg_match('/rated-(\d+)/', $class, $m) === 1 ? (int) $m[1] : null;
    }
}
