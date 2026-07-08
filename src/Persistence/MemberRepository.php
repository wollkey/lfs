<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Member;

final class MemberRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function save(Member $member): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO members (username, display_name, status)
                VALUES (:username, :name, :status)
                ON CONFLICT (username) DO UPDATE
                    SET display_name = excluded.display_name,
                        status       = excluded.status
            SQL);
        $stmt->execute([
            'username' => $member->username,
            'name' => $member->displayName,
            'status' => $member->status->value,
        ]);
    }

    public function find(string $username): ?Member
    {
        $stmt = $this->pdo->prepare('SELECT username, display_name FROM members WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        return $row ? new Member($row['username'], $row['display_name']) : null;
    }

    /**
     * @return Member[]
     */
    public function all(): array
    {
        return array_map(
            static fn (array $r) => new Member($r['username'], $r['display_name']),
            $this->pdo->query('SELECT username, display_name FROM members ORDER BY display_name')->fetchAll(),
        );
    }
}
