<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Member;
use App\Persistence\MemberRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'members:move',
    description: 'Move a member to another place in the picking queue; everyone in between shifts by one.',
)]
final class MoveMemberCommand extends Command
{
    private const string END = 'end';

    public function __construct(
        private readonly MemberRepository $members,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Member to move, by username.')]
        ?string $username = null,
        #[Argument(description: 'Where to move them: a place from 1 to the queue length, or "end".')]
        ?string $to = null,
    ): int {
        $queue = $this->members->rotation();
        if (count($queue) < 2) {
            $io->error('The picking queue is too short to reorder.');

            return Command::INVALID;
        }

        if ($username === null || $to === null) {
            $io->section('Picking queue');
            $this->printQueue($io, $queue);
        }

        $username ??= $this->askMember($io, $queue);
        $from = array_search($username, array_column($queue, 'username'), true);
        if ($from === false) {
            $io->error("No member with a place in the queue: \"{$username}\".");

            return Command::INVALID;
        }

        $place = $to === null ? $this->askPlace($io, count($queue)) : $this->parsePlace($to, count($queue));
        if ($place === null) {
            $io->error("Not a place in the queue: \"{$to}\".");

            return Command::INVALID;
        }

        $moved = $queue[$from];
        if ($place - 1 === $from) {
            $io->warning(sprintf('%s is %d in the queue already. Nothing changed.', $moved->displayName, $place));

            return Command::SUCCESS;
        }

        $order = $queue;
        array_splice($order, $from, 1);
        array_splice($order, $place - 1, 0, [$moved]);

        $this->members->reposition(array_column($order, 'username'));

        $io->section('New queue');
        $this->printQueue($io, $order, $moved->username);
        $io->success(sprintf('%s moved from %d to %d.', $moved->displayName, $from + 1, $place));

        return Command::SUCCESS;
    }

    /**
     * @param list<Member> $queue
     */
    private function printQueue(SymfonyStyle $io, array $queue, ?string $highlight = null): void
    {
        $rows = [];
        foreach ($queue as $i => $member) {
            $label = self::label($member);
            $rows[] = [$i + 1, $member->username === $highlight ? "<info>{$label}</info>" : $label];
        }

        $io->table(['#', 'Member'], $rows);
    }

    /**
     * @param list<Member> $queue
     */
    private function askMember(SymfonyStyle $io, array $queue): string
    {
        $usernameByLabel = [];
        foreach ($queue as $i => $member) {
            $usernameByLabel[sprintf('%d. %s', $i + 1, self::label($member))] = $member->username;
        }

        return $usernameByLabel[$io->choice('Who moves?', array_keys($usernameByLabel))];
    }

    private function askPlace(SymfonyStyle $io, int $size): int
    {
        return $io->ask(
            sprintf('New place (1–%d, or "%s")', $size, self::END),
            self::END,
            function (string $answer) use ($size): int {
                $place = $this->parsePlace($answer, $size);
                if ($place === null) {
                    throw new \RuntimeException(sprintf('Enter a number from 1 to %d, or "%s".', $size, self::END));
                }

                return $place;
            },
        );
    }

    private function parsePlace(string $to, int $size): ?int
    {
        $raw = strtolower(trim($to));
        if ($raw === self::END) {
            return $size;
        }
        if (!ctype_digit($raw)) {
            return null;
        }

        $place = (int) $raw;

        return $place >= 1 && $place <= $size ? $place : null;
    }

    private static function label(Member $member): string
    {
        return sprintf('%s (@%s)', $member->displayName, $member->username);
    }
}
