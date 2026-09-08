<?php

declare(strict_types=1);

namespace App\Seeding;

use App\Domain\Member;
use App\Letterboxd\Parser\ActivityParser;
use App\Letterboxd\Parser\FriendsRatingsParser;
use App\Letterboxd\Parser\ListParser;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\RatingRepository;

final readonly class ScrapedRatings
{
    public function __construct(
        private ListParser $listParser,
        private FriendsRatingsParser $friendsParser,
        private ActivityParser $activityParser,
        private FilmRepository $films,
        private MemberRepository $members,
        private RatingRepository $ratings,
    ) {
    }

    /**
     * @return array{added: int, updated: int, skipped: list<string>}
     */
    public function import(string $htmlDir): array
    {
        $known = array_flip($this->films->slugs());
        $roster = array_flip(array_map(static fn (Member $m) => $m->username, $this->members->all()));
        $before = $this->ratings->scores();

        $added = 0;
        $updated = 0;
        $skipped = [];

        foreach ($this->scraped($htmlDir, $known, $roster, $skipped) as $key => $score) {
            [$slug, $username] = explode('|', $key, 2);

            $previous = $before[$key] ?? null;
            if ($previous === $score) {
                continue;
            }

            $this->ratings->setRating($slug, $username, $score);
            $previous === null ? ++$added : ++$updated;
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => array_values(array_unique($skipped))];
    }

    /**
     * @param array<string, int> $known
     * @param array<string, int> $roster
     * @param list<string>       $skipped
     *
     * @return array<string, int>
     */
    private function scraped(string $htmlDir, array $known, array $roster, array &$skipped): array
    {
        // Later sources overwrite earlier ones; activity is the freshest.
        return [
            ...$this->fromList($htmlDir, $known, $roster, $skipped),
            ...$this->fromFriends($htmlDir, $known, $roster, $skipped),
            ...$this->fromActivity($htmlDir, $known, $roster, $skipped),
        ];
    }

    /**
     * @param array<string, int> $known
     * @param array<string, int> $roster
     * @param list<string>       $skipped
     *
     * @return array<string, int>
     */
    private function fromList(string $htmlDir, array $known, array $roster, array &$skipped): array
    {
        $file = "{$htmlDir}/list.html";
        if (!is_file($file)) {
            return [];
        }

        $scores = [];
        foreach ($this->listParser->ownerRatings((string) file_get_contents($file)) as $slug => $rating) {
            if ($this->accepted($slug, $rating->username, $known, $roster, $skipped)) {
                $scores["{$slug}|{$rating->username}"] = $rating->rating;
            }
        }

        return $scores;
    }

    /**
     * @param array<string, int> $known
     * @param array<string, int> $roster
     * @param list<string>       $skipped
     *
     * @return array<string, int>
     */
    private function fromFriends(string $htmlDir, array $known, array $roster, array &$skipped): array
    {
        $scores = [];

        foreach (glob("{$htmlDir}/friends/*.html") ?: [] as $file) {
            $slug = basename($file, '.html');

            foreach ($this->friendsParser->parse((string) file_get_contents($file)) as $rating) {
                if ($this->accepted($slug, $rating->username, $known, $roster, $skipped)) {
                    $scores["{$slug}|{$rating->username}"] = $rating->rating;
                }
            }
        }

        return $scores;
    }

    /**
     * @param array<string, int> $known
     * @param array<string, int> $roster
     * @param list<string>       $skipped
     *
     * @return array<string, int>
     */
    private function fromActivity(string $htmlDir, array $known, array $roster, array &$skipped): array
    {
        $scores = [];

        foreach (glob("{$htmlDir}/friends_activity/*.html") ?: [] as $file) {
            $username = basename($file, '.html');

            foreach ($this->activityParser->parse((string) file_get_contents($file)) as $slug => $score) {
                if ($this->accepted($slug, $username, $known, $roster, $skipped)) {
                    $scores["{$slug}|{$username}"] = $score;
                }
            }
        }

        return $scores;
    }

    /**
     * @param array<string, int> $known
     * @param array<string, int> $roster
     * @param list<string>       $skipped
     */
    private function accepted(string $slug, string $username, array $known, array $roster, array &$skipped): bool
    {
        if (!isset($known[$slug])) {
            return false;
        }

        if (!isset($roster[$username])) {
            $skipped[] = $username;

            return false;
        }

        return true;
    }
}
