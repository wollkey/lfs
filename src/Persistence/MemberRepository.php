<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Member;
use App\Domain\MemberStatus;

final readonly class MemberRepository
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function save(Member $member): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO members (username, display_name, status, position)
                VALUES (:username, :name, :status, :position)
                ON CONFLICT (username) DO UPDATE
                    SET display_name = excluded.display_name,
                        status       = excluded.status,
                        position     = excluded.position
            SQL);

        $stmt->execute([
            'username' => $member->username,
            'name' => $member->displayName,
            'status' => $member->status->value,
            'position' => $member->position,
        ]);
    }

    public function find(string $username): ?Member
    {
        $stmt = $this->pdo->prepare('SELECT username, display_name, position FROM members WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        return $row ? new Member($row['username'], $row['display_name'], position: $row['position']) : null;
    }

    /**
     * @return Member[]
     */
    public function all(): array
    {
        return array_map(
            static fn (array $r) => new Member($r['username'], $r['display_name'], MemberStatus::from($r['status']), $r['position']),
            $this->pdo->query('SELECT username, display_name, status, position FROM members ORDER BY display_name')->fetchAll(),
        );
    }
}
