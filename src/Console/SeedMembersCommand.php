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
    private const ROSTER = [
        ['wollkey',        'Лёша',      MemberStatus::Active],
        ['lenka_penka',    'Лена',      MemberStatus::Active],
        ['al1vka',         'Алина Б.',  MemberStatus::Active],
        ['alinafrolova',   'Алина Ф.',  MemberStatus::Active],
        ['vika',           'Вика',      MemberStatus::Active],
        ['psy667',         'Сеня',      MemberStatus::Active],
        ['christallisme',  'Кристина',  MemberStatus::Active],
        ['atomic_rage',    'Дима',      MemberStatus::Active],
        ['nickbiryukov',   'Никита',    MemberStatus::Active],
        ['vans_von_trier', 'Ваня',      MemberStatus::Active],
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
        foreach (self::ROSTER as [$username, $name, $status]) {
            $this->members->save(new Member($username, $name, $status));
        }

        $io->success(sprintf('Seeded %d members.', count(self::ROSTER)));

        return Command::SUCCESS;
    }
}
