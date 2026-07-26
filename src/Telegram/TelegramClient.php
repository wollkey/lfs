<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\ApiException;

interface TelegramClient
{
    /**
     * @throws ApiException
     */
    public function send(string $chatId, Post $post): void;

    /**
     * @throws ApiException
     */
    public function setWebhook(string $url, #[\SensitiveParameter] string $secretToken): void;
}
