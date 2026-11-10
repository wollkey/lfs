<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Domain\MemberStatus;
use App\Statistics\Ceremony\Ceremony;
use App\Statistics\Ceremony\CeremonyYear;
use App\Statistics\Ceremony\InvalidNominations;
use App\Statistics\Ceremony\Nomination;
use App\Statistics\Ceremony\Nominee;
use App\Statistics\Statistics;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Ceremony::class)]
final class CeremonyTest extends IntegrationTestCase
{
    private string $nominationsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nominationsDir = sys_get_temp_dir().'/lfs-ceremony-'.bin2hex(random_bytes(4));
        mkdir($this->nominationsDir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->nominationsDir.'/*') ?: []);
        rmdir($this->nominationsDir);
    }

    public function testSavesTheFilmOfTheYearForTheEnd(): void
    {
        $ceremony = $this->ceremony2025();

        self::assertSame([
            'Провал года',
            'Самый спорный фильм',
            'Горячая оценка',
            'Лучший выбор',
            'Самый активный',
            'Самые высокие оценки',
            'Самые низкие оценки',
            'Фильм года',
        ], $this->titles($ceremony));
        self::assertEquals(new Nomination('Фильм года', 'Самая высокая средняя оценка клуба', [
            new Nominee('Sicario', null, 'sicario', '7.3', true),
            new Nominee('Arrival', null, 'arrival', '7.0', false),
        ]), $ceremony->nominations[7]);
    }

    public function testEveryoneTiedForTheTopWinsEvenPastTheNomineeLimit(): void
    {
        $active = $this->nomination($this->ceremony2025(), 'Самый активный');

        self::assertSame(['Al1vka', 'Christallisme', 'Wollkey'], array_map(static fn (Nominee $n): string => $n->name, $active->nominees));
        self::assertSame([true, true, true], array_map(static fn (Nominee $n): bool => $n->winner, $active->nominees));
        self::assertSame('3 оценки', $active->nominees[0]->value);
    }

    public function testAMemberIsNominatedOnceForTheirStrongestEntry(): void
    {
        $hotTake = $this->nomination($this->ceremony2025(), 'Горячая оценка');

        self::assertEquals([
            new Nominee('Christallisme', 'Stalker', 'stalker', '3 при средней 6.5', true),
            new Nominee('Wollkey', 'Stalker', 'stalker', '10 при средней 6.5', true),
        ], $hotTake->nominees);
    }

    public function testTheBestPickNamesThePickerAndTheFilm(): void
    {
        $bestPick = $this->nomination($this->ceremony2025(), 'Лучший выбор');

        self::assertEquals(new Nominee('Christallisme', 'Sicario', 'sicario', '7.3', true), $bestPick->nominees[0]);
    }

    public function testTheOwnersNominationsGoRightBeforeTheFilmOfTheYear(): void
    {
        file_put_contents($this->nominationsDir.'/2025.json', (string) json_encode([[
            'title' => 'Кадр года',
            'lead' => 'Выбор ведущего',
            'nominees' => [['name' => 'Лена', 'detail' => 'Сцена в Зоне', 'film' => 'stalker'], ['name' => 'Лёша']],
            'winner' => 1,
        ]]));

        $ceremony = $this->ceremony2025();

        self::assertSame(['Кадр года', 'Фильм года'], array_slice($this->titles($ceremony), -2));
        self::assertEquals(new Nomination('Кадр года', 'Выбор ведущего', [
            new Nominee('Лена', 'Сцена в Зоне', 'stalker', null, false),
            new Nominee('Лёша', null, null, null, true),
        ]), $ceremony->nominations[7]);
    }

    public function testRejectsAMalformedNominationsFile(): void
    {
        file_put_contents($this->nominationsDir.'/2025.json', '{"title": "Кадр года"}');

        $this->expectException(InvalidNominations::class);

        $this->ceremony2025();
    }

    public function testReturnsNullForAYearWithoutRatings(): void
    {
        $this->seedYear();

        self::assertNull($this->ceremony()->year(2024));
    }

    private function ceremony2025(): CeremonyYear
    {
        $this->seedYear();

        $ceremony = $this->ceremony()->year(2025);
        self::assertNotNull($ceremony);

        return $ceremony;
    }

    private function ceremony(): Ceremony
    {
        return new Ceremony($this->pdo, new Statistics($this->pdo), $this->nominationsDir, quorum: 2, minRatings: 2, nominees: 2);
    }

    private function seedYear(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenMember('lenka_penka', MemberStatus::Former);
        $this->givenRound(1);

        $this->givenFilmRatedBy('arrival', ['al1vka' => 9, 'christallisme' => 8, 'wollkey' => 4]);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2025-01-06');
        $this->givenFilmRatedBy('sicario', ['al1vka' => 6, 'christallisme' => 7, 'wollkey' => 9]);
        $this->rounds->addFilm(1, 'sicario', 'christallisme', 2, '2025-02-03');
        $this->givenFilmRatedBy('stalker', ['al1vka' => 4, 'christallisme' => 3, 'wollkey' => 10, 'lenka_penka' => 9]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 3, '2025-03-03');
        $this->givenFilmRatedBy('tenet', ['al1vka' => 10, 'christallisme' => 1]);
        $this->rounds->addFilm(1, 'tenet', 'al1vka', 4, '2026-01-05');
    }

    /**
     * @return list<string>
     */
    private function titles(CeremonyYear $ceremony): array
    {
        return array_map(static fn (Nomination $n): string => $n->title, $ceremony->nominations);
    }

    private function nomination(CeremonyYear $ceremony, string $title): Nomination
    {
        foreach ($ceremony->nominations as $nomination) {
            if ($nomination->title === $title) {
                return $nomination;
            }
        }

        self::fail("No «{$title}» nomination.");
    }
}
