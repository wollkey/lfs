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
     * @return array{slug: string, title: string, round: int, position: int}|null null when the page is unreachable
     */
    public function add(string $slug, ?string $pickedBy, string $pickedOn): ?array
    {
        $film = $this->page->fetch($slug);
        if ($film === null) {
            return null;
        }

        // file_get_contents follows /film/drive/ to /film/drive-2011/ silently.
        $slug = $film->slug;

        $this->films->save(new Film($slug, $film->title));

        [$round, $position] = $this->slot($slug);

        $this->rounds->ensure($round);
        $this->rounds->addFilm($round, $slug, $pickedBy, $position, $pickedOn);

        return ['slug' => $slug, 'title' => $film->title, 'round' => $round, 'position' => $position];
    }

    /**
     * A round holds one pick per active member; the film keeps its slot once it has one.
     *
     * @return array{int, int}
     */
    private function slot(string $slug): array
    {
        $existing = $this->rounds->slotOf($slug);
        if ($existing !== null) {
            return $existing;
        }

        $last = $this->rounds->lastRound() ?? 1;

        return $this->rounds->filmCount($last) >= count($this->members->active())
            ? [$last + 1, 1]
            : [$last, $this->rounds->maxPosition($last) + 1];
    }
}
