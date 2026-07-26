<?php

declare(strict_types=1);

namespace App\Console;

use App\Telegram\Exception\TelegramException;
use App\Telegram\TelegramClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bot:set-webhook', description: 'Register the Telegram webhook URL for this bot.')]
final class SetWebhookCommand extends Command
{
    public function __construct(
        private readonly ?TelegramClient $client,
        #[\SensitiveParameter]
        private readonly ?string $secretToken,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Public HTTPS endpoint, e.g. https://host/api/telegram/webhook')]
        string $url,
    ): int {
        if ($this->client === null || $this->secretToken === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET first.');

            return Command::INVALID;
        }

        try {
            $this->client->setWebhook($url, $this->secretToken);
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Webhook set: '.$url);

        return Command::SUCCESS;
    }
}
