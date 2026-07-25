<?php

declare(strict_types=1);

namespace App\Http\Controller\Ingest;

use App\Http\BadRequest;

final class TargetPath
{
    private const string NAME = '/^[a-z0-9][a-z0-9_-]*$/';

    /**
     * @throws BadRequest on an unknown type or unsafe name
     */
    public static function resolve(string $dataDir, string $type, string $name): string
    {
        $dataDir = rtrim($dataDir, '/');

        if ($type === 'list') {
            return "{$dataDir}/list.html";
        }

        $subdir = match ($type) {
            'friends' => 'friends',
            'activity' => 'friends_activity',
            default => throw new BadRequest("Unknown ingest type: {$type}."),
        };

        // Untrusted name — whitelist to block path traversal.
        if (preg_match(self::NAME, $name) !== 1) {
            throw new BadRequest("Invalid name: {$name}.");
        }

        return "{$dataDir}/{$subdir}/{$name}.html";
    }
}
