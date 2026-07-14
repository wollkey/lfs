<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Member;
use App\Domain\MemberStatus;
use App\Persistence\MemberRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'members:seed', description: 'Insert or update the current club roster.')]
final class SeedMembersCommand extends Command
{
    /**
     * @var list<array{string, string, MemberStatus}>
     */
    private const array ROSTER = [
        ['lenka_penka',    'Лена',      MemberStatus::Active],
        ['christallisme',  'Кристина',  MemberStatus::Active],
        ['psy667',         'Сеня',      MemberStatus::Active],
        ['wollkey',        'Лёша',      MemberStatus::Active],
        ['al1vka',         'Алина Б.',  MemberStatus::Active],
        ['atomic_rage',    'Дима',      MemberStatus::Active],
        ['nickbiryukov',   'Никита',    MemberStatus::Active],
        ['vans_von_trier', 'Ваня',      MemberStatus::Active],
        ['vika',           'Вика',      MemberStatus::Active],
        ['alinafrolova',   'Алина Ф.',  MemberStatus::Active],

        ['justdanya',      'Данил П.', MemberStatus::Former],
        ['koshmarus',      'Данил К.', MemberStatus::Former],
    ];

    public function __construct(
        private readonly MemberRepository $members,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $position = 0;

        foreach (self::ROSTER as [$username, $name, $status]) {
            $isActive = $status === MemberStatus::Active;

            $this->members->save(new Member(
                username: $username,
                displayName: $name,
                status: $status,
                position: $isActive ? ++$position : null,
            ));
        }

        $io->success(sprintf('Seeded %d members (%d active).', count(self::ROSTER), $position));

        return Command::SUCCESS;
    }
}
