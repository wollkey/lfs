<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\TelegramException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MicroPoster
{
    public function __construct(
        private ?TelegramClient $client,
        private ?string $chatId,
    ) {
    }

    public function post(SymfonyStyle $io, ?Post $post, bool $dryRun): int
    {
        if ($post === null) {
            $io->success('Nothing to post this time.');

            return Command::SUCCESS;
        }

        $caption = $post->intro ?? $post->title;
        $posters = array_values(array_filter($post->images, is_file(...)));

        if ($dryRun) {
            $io->writeln($caption);
            foreach ($post->images as $image) {
                $io->writeln((is_file($image) ? '✓ ' : '✗ ').$image);
            }

            return Command::SUCCESS;
        }

        if ($this->client === null || $this->chatId === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to post.');

            return Command::INVALID;
        }

        try {
            if (count($posters) === 1) {
                $this->client->sendPhoto($this->chatId, $posters[0], $caption, html: true);
            } elseif ($posters !== []) {
                $this->client->sendPhotoGroup($this->chatId, $posters, $caption, html: true);
            } else {
                $this->client->send($this->chatId, new Post($post->title, intro: strip_tags($caption)));
            }
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success($posters !== [] ? 'Posted.' : 'Posted without a poster (text fallback).');

        return Command::SUCCESS;
    }
}
