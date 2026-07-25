<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\BadRequest;
use App\Http\Controller\Ingest\TargetPath;

/**
 * Dev-only: stores a scraped page as HTML under data/ for `make seed`.
 */
final readonly class IngestController
{
    public function __construct(
        private string $dataDir,
    ) {
    }

    /**
     * @return array{ok: true, path: string}
     */
    public function __invoke(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new BadRequest('POST required.');
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new BadRequest('Invalid JSON body.');
        }

        $type = $payload['type'] ?? null;
        $name = $payload['name'] ?? '';
        $html = $payload['html'] ?? null;

        if (!is_string($type) || !is_string($name) || !is_string($html) || $html === '') {
            throw new BadRequest('Expected string type, name and non-empty html.');
        }

        $path = TargetPath::resolve($this->dataDir, $type, $name);

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}.");
        }

        if (file_put_contents($path, $html) === false) {
            throw new \RuntimeException("Cannot write file: {$path}.");
        }

        return ['ok' => true, 'path' => ltrim(str_replace($this->dataDir, '', $path), '/')];
    }
}
