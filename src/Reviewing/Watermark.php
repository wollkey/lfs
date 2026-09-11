<?php

declare(strict_types=1);

namespace App\Reviewing;

final readonly class Watermark
{
    public function __construct(
        private string $path,
    ) {
    }

    public function value(): int
    {
        $raw = is_file($this->path) ? trim((string) file_get_contents($this->path)) : '';

        return ctype_digit($raw) ? (int) $raw : 0;
    }

    public function moveTo(int $updateId): void
    {
        if ($updateId <= $this->value()) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        file_put_contents($this->path, $updateId.PHP_EOL);
    }

    public function describe(): string
    {
        return $this->path;
    }
}
