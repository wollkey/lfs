<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\TelegramException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MicroPoster
{
    private const int CAPTION_LIMIT = 1024;

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
            if ($posters === []) {
                $this->client->sendText($this->chatId, $caption);
            } elseif ($this->captionLength($caption) <= self::CAPTION_LIMIT) {
                $this->sendPosters($this->client, $this->chatId, $posters, $caption);
            } else {
                $this->sendPosters($this->client, $this->chatId, $posters, '');
                $this->client->sendText($this->chatId, $caption);
            }
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success($posters !== [] ? 'Posted.' : 'Posted without a poster (text fallback).');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $posters
     *
     * @throws TelegramException
     */
    private function sendPosters(TelegramClient $client, string $chatId, array $posters, string $caption): void
    {
        if (count($posters) === 1) {
            $client->sendPhoto($chatId, $posters[0], $caption, html: true);
        } else {
            $client->sendPhotoGroup($chatId, $posters, $caption, html: true);
        }
    }

    private function captionLength(string $caption): int
    {
        $visible = html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return intdiv(strlen(mb_convert_encoding($visible, 'UTF-16LE', 'UTF-8')), 2);
    }
}
