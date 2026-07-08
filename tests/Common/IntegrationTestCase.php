<?php

declare(strict_types=1);

namespace App\Tests\Common;

use App\Domain\Film;
use App\Domain\Member;
use App\Domain\MemberStatus;
use App\Domain\Round;
use App\Persistence\Connection;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\Migrator;
use App\Persistence\RatingRepository;
use App\Persistence\RoundRepository;
use App\Statistics\Statistics;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected \PDO $pdo;
    protected MemberRepository $members;
    protected FilmRepository $films;
    protected RoundRepository $rounds;
    protected RatingRepository $ratings;

    protected function setUp(): void
    {
        $this->pdo = Connection::open(':memory:');                       // свежая БД на каждый тест
        new Migrator($this->pdo, LFS_ROOT.'/migrations')->migrate();

        $this->members = new MemberRepository($this->pdo);
        $this->films = new FilmRepository($this->pdo);
        $this->rounds = new RoundRepository($this->pdo);
        $this->ratings = new RatingRepository($this->pdo);
    }

    protected function statistics(int $quorum = 5): Statistics
    {
        return new Statistics($this->pdo, $quorum);
    }

    protected function givenMembers(string ...$usernames): void
    {
        foreach ($usernames as $username) {
            $this->givenMember($username);
        }
    }

    protected function givenMember(string $username, MemberStatus $status = MemberStatus::Active): void
    {
        $this->members->save(new Member($username, ucfirst($username), $status));
    }

    protected function givenRound(int $number, ?string $startedOn = null, ?string $endedOn = null): void
    {
        $this->rounds->save(new Round($number, $startedOn, $endedOn));
    }

    /**
     * @param array<string,int> $scores username => score
     */
    protected function givenFilmRatedBy(string $slug, array $scores): void
    {
        $this->films->save(new Film($slug, ucfirst($slug)));

        foreach ($scores as $username => $score) {
            $this->ratings->setRating($slug, $username, $score);
        }
    }
}
