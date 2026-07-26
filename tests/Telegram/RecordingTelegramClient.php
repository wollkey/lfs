<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Exception\ApiException;
use App\Telegram\Post;
use App\Telegram\TelegramClient;

final class RecordingTelegramClient implements TelegramClient
{
    /**
     * @var list<array{chatId: string, post: Post}>
     */
    public array $sent = [];

    /**
     * @var list<array{url: string, secretToken: string}>
     */
    public array $webhooks = [];

    public function __construct(
        private readonly bool $fail = false,
    ) {
    }

    public function send(string $chatId, Post $post): void
    {
        if ($this->fail) {
            throw new ApiException('simulated failure');
        }

        $this->sent[] = ['chatId' => $chatId, 'post' => $post];
    }

    public function setWebhook(string $url, #[\SensitiveParameter] string $secretToken): void
    {
        if ($this->fail) {
            throw new ApiException('simulated failure');
        }

        $this->webhooks[] = ['url' => $url, 'secretToken' => $secretToken];
    }
}
