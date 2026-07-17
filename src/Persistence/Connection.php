<?php

declare(strict_types=1);

namespace App\Persistence;

final class Connection
{
    /**
     * Open the database for reading and writing. Used locally for migrations,
     * seeding and rating.
     */
    public static function open(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:'.$path);
        self::configure($pdo);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /**
     * Open the database read-only for a file on read-only media. Skips locking
     * and WAL side files, so it works when the database is mounted read-only.
     * Used on production, where nothing writes to the database.
     */
    public static function openReadOnly(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:file:'.$path.'?immutable=1&mode=ro');
        self::configure($pdo);

        return $pdo;
    }

    private static function configure(\PDO $pdo): void
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }
}
