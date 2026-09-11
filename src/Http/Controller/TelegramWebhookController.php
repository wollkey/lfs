<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\BadRequest;
use App\Telegram\Inbox\Capture;
use App\Telegram\Inbox\MessageLog;

final readonly class TelegramWebhookController
{
    public function __construct(
        private Capture $capture,
        private MessageLog $log,
        #[\SensitiveParameter]
        private string $secretToken,
    ) {
    }

    /**
     * @return array{ok: true}
     */
    public function __invoke(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new BadRequest('POST required.');
        }

        $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!is_string($secret) || !hash_equals($this->secretToken, $secret)) {
            throw new BadRequest('Invalid secret token.');
        }

        $update = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($update)) {
            throw new BadRequest('Invalid JSON body.');
        }

        $message = $this->capture->fromUpdate($update);
        if ($message !== null) {
            $this->log->append($message);
        }

        return ['ok' => true];
    }
}
