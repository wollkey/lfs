<?php

declare(strict_types=1);

namespace App\Console;

use App\Statistics\Statistics;
use App\Telegram\Card\PanelsCard;
use App\Telegram\Card\StandingsCard;
use App\Telegram\Exception\RenderException;
use App\Telegram\Exception\TelegramException;
use App\Telegram\Messages;
use App\Telegram\Post;
use App\Telegram\Rasterizer;
use App\Telegram\TelegramClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bot:post-summary', description: 'Post the end-of-round summary as a single Telegram album.')]
final class PostRoundSummaryCommand extends Command
{
    public function __construct(
        private readonly Messages $messages,
        private readonly Statistics $stats,
        private readonly StandingsCard $standingsCard,
        private readonly PanelsCard $panelsCard,
        private readonly Rasterizer $rasterizer,
        private readonly ?TelegramClient $client,
        private readonly ?string $chatId,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Round number to summarise (defaults to the current round).')]
        ?int $round = null,
        #[Option(description: 'Write the cards to var/ instead of posting them.')]
        bool $dryRun = false,
    ): int {
        $round ??= $this->stats->currentRound();

        if ($round === null) {
            $io->error('No rounds to summarise.');

            return Command::INVALID;
        }

        ['caption' => $caption, 'cards' => $cards] = $this->messages->roundSummary($round);

        if ($dryRun) {
            return $this->dryRun($io, $caption, $cards);
        }

        if ($this->client === null || $this->chatId === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to post.');

            return Command::INVALID;
        }

        try {
            if ($cards === []) {
                $this->client->send($this->chatId, new Post(sprintf('Круг %d', $round), intro: $caption));
            } else {
                $this->postAlbum($this->client, $this->chatId, $caption, $cards);
            }
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Posted the round %d summary.', $round));

        return Command::SUCCESS;
    }

    /**
     * @param Post[] $cards
     */
    private function postAlbum(TelegramClient $client, string $chatId, string $caption, array $cards): void
    {
        $imagePaths = $this->renderCards($cards);

        if ($imagePaths === null) {
            $client->send($chatId, new Post('🎬 Итоги круга', intro: $caption));

            return;
        }

        try {
            $client->sendPhotoGroup($chatId, $imagePaths, $caption);
        } finally {
            foreach ($imagePaths as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * @param Post[] $cards
     *
     * @return string[]|null
     */
    private function renderCards(array $cards): ?array
    {
        $paths = [];
        foreach ($cards as $card) {
            try {
                $paths[] = $this->rasterizer->toPng($this->renderSvg($card));
            } catch (RenderException) {
                foreach ($paths as $path) {
                    @unlink($path);
                }

                return null;
            }
        }

        return $paths;
    }

    /**
     * @param Post[] $cards
     */
    private function dryRun(SymfonyStyle $io, string $caption, array $cards): int
    {
        $io->writeln($caption);
        $io->newLine();

        $dir = dirname(__DIR__, 2).'/var';
        @mkdir($dir, 0o775, true);

        foreach ($cards as $index => $card) {
            $number = $index + 1;
            $svg = $this->renderSvg($card);
            file_put_contents(sprintf('%s/summary-%d.svg', $dir, $number), $svg);

            try {
                rename($this->rasterizer->toPng($svg), sprintf('%s/summary-%d.png', $dir, $number));
            } catch (RenderException $e) {
                $io->warning(sprintf('Wrote summary-%d.svg, but rasterizing failed: %s', $number, $e->getMessage()));
            }
        }

        $io->success('Wrote the summary cards to var/.');

        return Command::SUCCESS;
    }

    private function renderSvg(Post $post): string
    {
        return $post->table !== null && $post->table->headers === []
            ? $this->panelsCard->render($post)
            : $this->standingsCard->render($post);
    }
}
