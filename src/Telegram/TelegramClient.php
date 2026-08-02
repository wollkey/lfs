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
    public function sendPhoto(string $chatId, string $imagePath, string $caption): void;

    /**
     * @param string[] $imagePaths
     *
     * @throws ApiException
     */
    public function sendPhotoGroup(string $chatId, array $imagePaths, string $caption): void;

    /**
     * @throws ApiException
     */
    public function setWebhook(string $url, #[\SensitiveParameter] string $secretToken): void;
}
