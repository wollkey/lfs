<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\BadRequest;
use App\Telegram\Messages;
use App\Telegram\TelegramClient;

final readonly class TelegramWebhookController
{
    public function __construct(
        private Messages $messages,
        private TelegramClient $client,
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

        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return ['ok' => true];
        }

        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        if (!is_string($text) || !is_int($chatId)) {
            return ['ok' => true];
        }

        $post = $this->messages->forCommand($text);
        if ($post === null) {
            return ['ok' => true];
        }

        $this->client->send((string) $chatId, $post);

        return ['ok' => true];
    }
}
