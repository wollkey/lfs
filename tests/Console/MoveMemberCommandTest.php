<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\MoveMemberCommand;
use App\Domain\Member;
use App\Domain\MemberStatus;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MoveMemberCommand::class)]
final class MoveMemberCommandTest extends IntegrationTestCase
{
    private const array ROSTER = [
        'lenka_penka' => 'Лена',
        'christallisme' => 'Кристина',
        'wollkey' => 'Лёша',
        'al1vka' => 'Алина Б.',
        'atomic_rage' => 'Дима',
        'nickbiryukov' => 'Никита',
        'psy667' => 'Сеня',
        'justdanya' => 'Данил П.',
    ];

    public function testMovingToTheEndShiftsEveryoneBehindOnePlaceUp(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey', 'al1vka', 'atomic_rage');

        self::assertSame(Command::SUCCESS, $this->console()->execute(['username' => 'al1vka', 'to' => 'end']));
        self::assertSame(
            ['lenka_penka', 'christallisme', 'wollkey', 'atomic_rage', 'al1vka'],
            $this->queue(),
        );
    }

    public function testMovingForwardShiftsEveryoneInBetweenOnePlaceBack(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey', 'al1vka');

        self::assertSame(Command::SUCCESS, $this->console()->execute(['username' => 'al1vka', 'to' => '2']));
        self::assertSame(['lenka_penka', 'al1vka', 'christallisme', 'wollkey'], $this->queue());
    }

    public function testInteractiveRunAsksWhoMovesAndWhereTo(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey');

        $console = $this->console();
        $console->setInputs(['2. Кристина (@christallisme)', 'end']);

        self::assertSame(Command::SUCCESS, $console->execute([]));
        self::assertSame(['lenka_penka', 'wollkey', 'christallisme'], $this->queue());
    }

    public function testAMemberOnABreakStaysOutOfTheRenumberedQueue(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey');
        $this->givenMember('psy667', displayName: self::ROSTER['psy667']);
        $this->givenMember('justdanya', MemberStatus::Former, 12, self::ROSTER['justdanya']);

        self::assertSame(Command::SUCCESS, $this->console()->execute(['username' => 'lenka_penka', 'to' => 'end']));
        self::assertSame(
            ['christallisme' => 1, 'wollkey' => 2, 'lenka_penka' => 3, 'justdanya' => 12, 'psy667' => null],
            $this->positions(),
        );
    }

    public function testMovingSomeoneOntoTheirOwnPlaceChangesNothing(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey');

        $console = $this->console();

        self::assertSame(Command::SUCCESS, $console->execute(['username' => 'christallisme', 'to' => '2']));
        self::assertStringContainsString('Кристина is 2 in the queue already', $console->getDisplay());
        self::assertSame(['lenka_penka', 'christallisme', 'wollkey'], $this->queue());
    }

    public function testAnUnknownMemberLeavesTheQueueAlone(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey');

        self::assertSame(Command::INVALID, $this->console()->execute(['username' => 'tarkovsky', 'to' => 'end']));
        self::assertSame(['lenka_penka', 'christallisme', 'wollkey'], $this->queue());
    }

    public function testAPlaceOutsideTheQueueLeavesTheQueueAlone(): void
    {
        $this->givenQueue('lenka_penka', 'christallisme', 'wollkey');

        self::assertSame(Command::INVALID, $this->console()->execute(['username' => 'wollkey', 'to' => '7']));
        self::assertSame(['lenka_penka', 'christallisme', 'wollkey'], $this->queue());
    }

    public function testAQueueOfOneCannotBeReordered(): void
    {
        $this->givenQueue('wollkey');

        self::assertSame(Command::INVALID, $this->console()->execute(['username' => 'wollkey', 'to' => 'end']));
    }

    private function givenQueue(string ...$usernames): void
    {
        foreach ($usernames as $i => $username) {
            $this->givenMember($username, position: $i + 1, displayName: self::ROSTER[$username]);
        }
    }

    /**
     * @return list<string>
     */
    private function queue(): array
    {
        return array_map(static fn (Member $member) => $member->username, $this->members->rotation());
    }

    /**
     * @return array<string, ?int>
     */
    private function positions(): array
    {
        $rows = $this->pdo->query('SELECT username, position FROM members ORDER BY position NULLS LAST, username');

        $positions = [];
        foreach ($rows->fetchAll() as $row) {
            $positions[$row['username']] = $row['position'] === null ? null : (int) $row['position'];
        }

        return $positions;
    }

    private function console(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new MoveMemberCommand($this->members));

        return new CommandTester($application->find('members:move'));
    }
}
