<?php

declare(strict_types=1);

namespace App\Persistence;

final readonly class Migrator
{
    public function __construct(
        private \PDO $pdo,
        private string $migrationsDir,
    ) {
    }

    /**
     * @return string[] версии, применённые в этом запуске
     */
    public function migrate(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new \RuntimeException("Migrations directory not found: {$this->migrationsDir}");
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)',
        );

        $applied = $this->pdo->query('SELECT version FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);

        $ran = [];
        foreach ($this->pending($applied) as $version => $file) {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec(file_get_contents($file));
                $stmt = $this->pdo->prepare('INSERT INTO migrations (version, applied_at) VALUES (:v, :t)');
                $stmt->execute(['v' => $version, 't' => date('c')]);
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException("Migration {$version} failed: {$e->getMessage()}", 0, $e);
            }
            $ran[] = $version;
        }

        return $ran;
    }

    /**
     * @param string[] $applied
     * @return array<string,string> version => path
     */
    private function pending(array $applied): array
    {
        $pending = [];
        foreach (glob($this->migrationsDir.'/*.sql') as $file) {
            $version = basename($file, '.sql');
            if (!in_array($version, $applied, true)) {
                $pending[$version] = $file;
            }
        }
        ksort($pending);

        return $pending;
    }
}
