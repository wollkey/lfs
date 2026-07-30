<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\AssignPickersCommand;
use App\Domain\Film;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AssignPickersCommand::class)]
final class AssignPickersCommandTest extends IntegrationTestCase
{
    public function testAssignsAndMarksExternalSoNeitherIsAskedAgain(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1);
        $this->givenRoundFilm('external', 1);
        $this->givenRoundFilm('new', 2);

        $tester = $this->tester();
        $tester->setInputs(['— mark as external (never ask again)', 'Wollkey (@wollkey)']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame([], $this->rounds->filmsWithoutPicker());
        self::assertNull($this->pickedBy('external'));
        self::assertSame('wollkey', $this->pickedBy('new'));
    }

    public function testSkipForNowLeavesTheFilmInTheWorklist(): void
    {
        $this->givenMembers('wollkey');
        $this->givenRound(1);
        $this->givenRoundFilm('later', 1);

        $tester = $this->tester();
        $tester->setInputs(['— skip for now (decide later)']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $remaining = $this->rounds->filmsWithoutPicker();
        self::assertCount(1, $remaining);
        self::assertSame('later', $remaining[0]['slug']);
    }

    private function givenRoundFilm(string $slug, int $position): void
    {
        $this->films->save(new Film($slug, ucfirst($slug)));
        $this->rounds->syncFilm(1, $slug, $position, '2025-01-06');
    }

    private function pickedBy(string $slug): ?string
    {
        $stmt = $this->pdo->prepare('SELECT picked_by FROM round_films WHERE film_slug = :slug');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetchColumn() ?: null;
    }

    private function tester(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new AssignPickersCommand($this->members, $this->rounds));

        return new CommandTester($application->find('rounds:pick'));
    }
}
