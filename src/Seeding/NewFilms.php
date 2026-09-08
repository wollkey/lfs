<?php

declare(strict_types=1);

namespace App\Seeding;

use App\Domain\Film;
use App\Domain\Member;
use App\Letterboxd\FilmPage;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\RoundRepository;

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
     * @return list<string>
     */
    public function pending(string $htmlDir): array
    {
        $placed = array_flip($this->rounds->placedFilms());

        $files = glob("{$htmlDir}/friends/*.html") ?: [];
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $pending = [];
        foreach ($files as $file) {
            $slug = basename($file, '.html');
            if (!isset($placed[$slug]) && preg_match(self::SLUG, $slug) === 1) {
                $pending[] = $slug;
            }
        }

        return $pending;
    }

    /**
     * @return array{slug: string, title: string, round: int, position: int, picker: ?string}|null
     */
    public function add(string $slug, string $pickedOn): ?array
    {
        $film = $this->page->fetch($slug);
        if ($film === null) {
            return null;
        }

        // The page may have redirected to a canonical slug.
        $slug = $film->slug;

        $this->films->save(new Film($slug, $film->title));

        $slot = $this->rounds->slotOf($slug) ?? $this->nextSlot();

        $this->rounds->ensure($slot['round']);
        $this->rounds->addFilm($slot['round'], $slug, $slot['picker'], $slot['position'], $pickedOn);

        return ['slug' => $slug, 'title' => $film->title, ...$slot];
    }

    /**
     * @return array{round: int, position: int, picker: ?string}
     */
    private function nextSlot(): array
    {
        $last = $this->rounds->lastRound() ?? 1;
        $full = $this->rounds->filmCount($last) >= count($this->rotation());

        $round = $full ? $last + 1 : $last;

        return [
            'round' => $round,
            'position' => $full ? 1 : $this->rounds->maxPosition($last) + 1,
            'picker' => $this->nextPicker($round),
        ];
    }

    private function nextPicker(int $round): ?string
    {
        $taken = array_flip($this->rounds->pickersIn($round));

        foreach ($this->rotation() as $member) {
            if (!isset($taken[$member->username])) {
                return $member->username;
            }
        }

        return null;
    }

    /**
     * @return list<Member>
     */
    private function rotation(): array
    {
        return array_values(array_filter(
            $this->members->active(),
            static fn (Member $member) => $member->position !== null,
        ));
    }
}
