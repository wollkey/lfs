<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Member;
use App\Domain\MemberStatus;
use App\Persistence\MemberRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'members:seed', description: 'Insert or update the club roster from data/roster.json.')]
final class SeedMembersCommand extends Command
{
    public function __construct(
        private readonly MemberRepository $members,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Roster JSON')]
        string $rosterFile = 'data/roster.json',
    ): int {
        if (!is_file($rosterFile)) {
            $io->error("Roster file not found: {$rosterFile}");

            return Command::INVALID;
        }

        $roster = json_decode((string) file_get_contents($rosterFile), true, flags: JSON_THROW_ON_ERROR);

        $position = 0;
        foreach ($roster as $entry) {
            $status = MemberStatus::from($entry['status']);
            $isActive = $status === MemberStatus::Active;

            $this->members->save(new Member(
                username: $entry['username'],
                displayName: $entry['displayName'],
                status: $status,
                position: $isActive ? ++$position : null,
            ));
        }

        $io->success(sprintf('Seeded %d members (%d active).', count($roster), $position));

        return Command::SUCCESS;
    }
}
