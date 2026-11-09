<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Domain\FilmDetails;
use App\Domain\MemberStatus;
use App\Domain\Review;
use App\Statistics\RatedFilm;
use App\Statistics\ScoredFilm;
use App\Statistics\Statistics;
use App\Statistics\TasteMatch;
use App\Statistics\Wrapped\LetterboxdGap;
use App\Statistics\Wrapped\MemberYear;
use App\Statistics\Wrapped\TagFigure;
use App\Statistics\Wrapped\Wrapped;
use App\Statistics\Wrapped\WrappedYear;
use App\Statistics\Wrapped\YearPick;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Wrapped::class)]
final class WrappedTest extends IntegrationTestCase
{
    public function testSumsUpTheClubsYear(): void
    {
        $club = $this->wrapped2025()->club;

        self::assertSame(3, $club->films);
        self::assertSame(116 + 121 + 162, $club->minutes);
        self::assertSame(10, $club->ratings);
        self::assertSame(1, $club->reviews);
        self::assertSame(['arrival'], $this->slugs($club->best));
        self::assertSame(['stalker'], $this->slugs($club->worst));
        self::assertSame(['stalker'], $this->slugs($club->divisive));
        self::assertEquals([new YearPick('arrival', 'Arrival', 'al1vka', 'Al1vka', 8.0, 3)], $club->bestPicks);
    }

    public function testFindsTheClubsDirectorGenreCountryAndOldestFilm(): void
    {
        $club = $this->wrapped2025()->club;

        self::assertEquals([new TagFigure('Denis Villeneuve', 2)], $club->directors);
        self::assertEquals([new TagFigure('Фантастика', 2, 7.1), new TagFigure('Драма', 2, 7.1)], $club->genres);
        self::assertEquals([new TagFigure('США', 2)], $club->countries);
        self::assertSame(2, $club->countryCount);
        self::assertSame('stalker', $club->oldest[0]->slug);
        self::assertSame(1979, $club->oldest[0]->value);
    }

    public function testTheClubsGenreNeedsMoreFilmsThanAMembers(): void
    {
        $this->seedYear();

        $wrapped = new Wrapped($this->pdo, new Statistics($this->pdo, minSharedFilms: 2), quorum: 3, minGenreFilms: 2, minClubGenreFilms: 3)->year(2025);

        self::assertNotNull($wrapped);
        self::assertSame([], $wrapped->club->genres);
        self::assertNotSame([], $wrapped->members[0]->genres);
    }

    public function testComparesTheClubWithLetterboxd(): void
    {
        $club = $this->wrapped2025()->club;

        self::assertSame([], $club->aboveLetterboxd);
        self::assertEquals([new LetterboxdGap('stalker', 'Stalker', 6.5, 8.8)], $club->belowLetterboxd);
    }

    public function testSumsUpTheYearOfEachActiveMember(): void
    {
        $wrapped = $this->wrapped2025();

        self::assertSame(['al1vka', 'christallisme', 'wollkey'], array_map(static fn (MemberYear $m): string => $m->username, $wrapped->members));
        self::assertSame([1, 1, 3], array_map(static fn (MemberYear $m): int => $m->rank, $wrapped->members));

        $al1vka = $wrapped->members[0];
        self::assertSame(3, $al1vka->watched);
        self::assertSame(399, $al1vka->minutes);
        self::assertSame(6.3, $al1vka->average);
        self::assertSame(-1.3, $al1vka->leaning);
        self::assertSame(0, $al1vka->reviews);
        self::assertEquals([new ScoredFilm('arrival', 'Arrival', 9, 8.0)], $al1vka->favorites);
        self::assertEquals([new ScoredFilm('stalker', 'Stalker', 4, 6.5)], $al1vka->leastFavorites);
        self::assertSame(['stalker'], $this->slugs($al1vka->hotTakes));
        self::assertEquals([new TagFigure('Фантастика', 2, 6.5), new TagFigure('Драма', 2, 6.5)], $al1vka->genres);
        self::assertSame(8.0, $al1vka->picksAverage);
        self::assertSame(['arrival'], $this->slugs($al1vka->bestPicks));
        self::assertEquals([new LetterboxdGap('stalker', 'Stalker', 4.0, 8.8)], $al1vka->letterboxd);
    }

    public function testFindsTheTasteTwinAndAntipodeWithinTheYear(): void
    {
        $al1vka = $this->wrapped2025()->members[0];

        self::assertEquals([new TasteMatch('christallisme', 'Christallisme', 67, 3)], $al1vka->closest);
        self::assertEquals([new TasteMatch('wollkey', 'Wollkey', 50, 2)], $al1vka->farthest);
    }

    public function testReturnsNullForAYearWithoutFilms(): void
    {
        $this->seedYear();

        self::assertNull($this->wrapped()->year(2024));
    }

    private function wrapped2025(): WrappedYear
    {
        $this->seedYear();

        $wrapped = $this->wrapped()->year(2025);
        self::assertNotNull($wrapped);

        return $wrapped;
    }

    private function wrapped(): Wrapped
    {
        return new Wrapped($this->pdo, new Statistics($this->pdo, minSharedFilms: 2), quorum: 3, minGenreFilms: 2, minClubGenreFilms: 2);
    }

    private function seedYear(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenMember('lenka_penka', MemberStatus::Former);
        $this->givenRound(1);

        $this->givenFilmRatedBy('arrival', ['al1vka' => 9, 'christallisme' => 8, 'lenka_penka' => 7]);
        $this->films->saveDetails('arrival', new FilmDetails(2016, 116, 4.0, ['Denis Villeneuve'], ['Science Fiction', 'Drama'], ['USA']));
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2025-01-06');

        $this->givenFilmRatedBy('sicario', ['al1vka' => 6, 'christallisme' => 7, 'wollkey' => 9]);
        $this->films->saveDetails('sicario', new FilmDetails(2015, 121, 3.7, ['Denis Villeneuve'], ['Crime', 'Thriller'], ['USA']));
        $this->rounds->addFilm(1, 'sicario', 'christallisme', 2, '2025-02-03');

        $this->givenFilmRatedBy('stalker', ['al1vka' => 4, 'christallisme' => 3, 'wollkey' => 10, 'lenka_penka' => 9]);
        $this->films->saveDetails('stalker', new FilmDetails(1979, 162, 4.4, ['Andrei Tarkovsky'], ['Science Fiction', 'Drama'], ['USSR']));
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 3, '2025-03-03');

        $this->givenFilmRatedBy('tenet', ['al1vka' => 10, 'wollkey' => 1]);
        $this->films->saveDetails('tenet', new FilmDetails(1920, 150, 1.0, ['Denis Villeneuve'], ['Drama'], ['USA']));
        $this->rounds->addFilm(1, 'tenet', 'al1vka', 4, '2026-01-05');

        $this->reviews->save(new Review('stalker', 'wollkey', 'Zone.'));
        $this->reviews->save(new Review('tenet', 'wollkey', 'Backwards.'));
    }

    /**
     * @param list<RatedFilm|ScoredFilm|YearPick> $films
     *
     * @return list<string>
     */
    private function slugs(array $films): array
    {
        return array_map(static fn (RatedFilm|ScoredFilm|YearPick $f): string => $f->slug, $films);
    }
}
