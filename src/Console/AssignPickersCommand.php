<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\MemberStatus;
use App\Persistence\MemberRepository;
use App\Persistence\RoundRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rounds:pick', description: 'Assign film pickers interactively for films that have none.')]
final class AssignPickersCommand extends Command
{
    private const string SKIP = '— skip (external list / decide later)';

    public function __construct(
        private readonly MemberRepository $members,
        private readonly RoundRepository $rounds,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $films = $this->rounds->filmsWithoutPicker();
        if ($films === []) {
            $io->success('Every film already has a picker. Nothing to assign.');

            return Command::SUCCESS;
        }

        $usernameByLabel = $this->activeMemberLabels();
        if ($usernameByLabel === []) {
            $io->error('No active members. Run members:seed first.');

            return Command::FAILURE;
        }

        $choices = [...array_keys($usernameByLabel), self::SKIP];
        $assigned = 0;
        $currentRound = null;

        foreach ($films as $film) {
            if ($film['round'] !== $currentRound) {
                $currentRound = $film['round'];
                $io->section(sprintf('Round %d', $currentRound));
            }

            $choice = $io->choice(sprintf('Who picked "%s"?', $film['title']), $choices, self::SKIP);
            if ($choice === self::SKIP) {
                continue;
            }

            $this->rounds->setPicker($film['round'], $film['slug'], $usernameByLabel[$choice]);
            ++$assigned;
        }

        $io->success(sprintf('Assigned %d picker(s).', $assigned));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> label => username, active members only
     */
    private function activeMemberLabels(): array
    {
        $labels = [];
        foreach ($this->members->all() as $member) {
            if ($member->status !== MemberStatus::Active) {
                continue;
            }
            $labels[sprintf('%s (@%s)', $member->displayName, $member->username)] = $member->username;
        }

        return $labels;
    }
}
