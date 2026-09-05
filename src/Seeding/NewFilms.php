<?php

declare(strict_types=1);

namespace App\Seeding;

use App\Domain\Film;
use App\Letterboxd\FilmPage;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\RoundRepository;

/**
 * Films the club has scraped but not recorded yet. The userscript names every
 * saved page after its film, so a page with an unknown slug is a new pick.
 */
final readonly class NewFilms
{
    private const string SLUG = '/^[a-z0-9][a-z0-9-]*$/';

    public function __construct(
        private FilmPage $page,
        private FilmRepository $films,
        private MemberRepository $members,
        private RoundRepository $rounds,
    ) {
    }

    /**
     * Scraped slugs the database does not know, newest scrape first.
     *
     * @return list<string>
     */
    public function pending(string $htmlDir): array
    {
        $known = array_flip($this->films->slugs());

        $files = glob("{$htmlDir}/friends/*.html") ?: [];
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $pending = [];
        foreach ($files as $file) {
            $slug = basename($file, '.html');
            if (!isset($known[$slug]) && preg_match(self::SLUG, $slug) === 1) {
                $pending[] = $slug;
            }
        }

        return $pending;
    }

    /**
     * @return array{slug: string, title: string, round: int, position: int, picker: ?string}|null
     *                                                                                             null when the page is unreachable
     */
    public function add(string $slug, string $pickedOn): ?array
    {
        $film = $this->page->fetch($slug);
        if ($film === null) {
            return null;
        }

        // file_get_contents follows /film/drive/ to /film/drive-2011/ silently.
        $slug = $film->slug;

        $this->films->save(new Film($slug, $film->title));

        // Keep whatever the film already has; only a fresh slot takes the next turn.
        $slot = $this->rounds->slotOf($slug) ?? $this->nextSlot();

        $this->rounds->ensure($slot['round']);
        $this->rounds->addFilm($slot['round'], $slug, $slot['picker'], $slot['position'], $pickedOn);

        return ['slug' => $slug, 'title' => $film->title, ...$slot];
    }

    /**
     * A round holds one pick per active member, taken in roster order.
     *
     * @return array{round: int, position: int, picker: ?string}
     */
    private function nextSlot(): array
    {
        $last = $this->rounds->lastRound() ?? 1;
        $full = $this->rounds->filmCount($last) >= count($this->members->active());

        $round = $full ? $last + 1 : $last;

        return [
            'round' => $round,
            'position' => $full ? 1 : $this->rounds->maxPosition($last) + 1,
            'picker' => $this->nextPicker($round),
        ];
    }

    /**
     * The active member, in roster order, whose turn has not come round yet.
     */
    private function nextPicker(int $round): ?string
    {
        $taken = array_flip($this->rounds->pickersIn($round));

        foreach ($this->members->active() as $member) {
            if ($member->position !== null && !isset($taken[$member->username])) {
                return $member->username;
            }
        }

        return null;
    }
}
