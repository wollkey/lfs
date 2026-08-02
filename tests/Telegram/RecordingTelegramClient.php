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
     * @var list<array{chatId: string, imagePath: string, caption: string}>
     */
    public array $photos = [];

    /**
     * @var list<array{chatId: string, imagePaths: string[], caption: string}>
     */
    public array $albums = [];

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

    public function sendPhoto(string $chatId, string $imagePath, string $caption): void
    {
        if ($this->fail) {
            throw new ApiException('simulated failure');
        }

        $this->photos[] = ['chatId' => $chatId, 'imagePath' => $imagePath, 'caption' => $caption];
    }

    public function sendPhotoGroup(string $chatId, array $imagePaths, string $caption): void
    {
        if ($this->fail) {
            throw new ApiException('simulated failure');
        }

        $this->albums[] = ['chatId' => $chatId, 'imagePaths' => $imagePaths, 'caption' => $caption];
    }

    public function setWebhook(string $url, #[\SensitiveParameter] string $secretToken): void
    {
        if ($this->fail) {
            throw new ApiException('simulated failure');
        }

        $this->webhooks[] = ['url' => $url, 'secretToken' => $secretToken];
    }
}
