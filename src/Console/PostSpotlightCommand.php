<?php

declare(strict_types=1);

namespace App\Console;

use App\Telegram\Card\SpotlightCard;
use App\Telegram\Exception\RenderException;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Telegram\Post;
use App\Telegram\Rasterizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bot:post-spotlight', description: 'Post the «Неделя славы» spotlight of the member who picked this week\'s film to Telegram.')]
final class PostSpotlightCommand extends Command
{
    public function __construct(
        private readonly Messages $messages,
        private readonly SpotlightCard $card,
        private readonly Rasterizer $rasterizer,
        private readonly MicroPoster $poster,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Print the caption and write the card to var/ instead of posting it.')]
        bool $dryRun = false,
    ): int {
        $spotlight = $this->messages->spotlight();
        if ($spotlight === null) {
            return $this->poster->post($io, null, $dryRun);
        }

        $svg = $this->card->render($spotlight);
        $image = $this->rasterize($svg);

        if ($dryRun && $image !== null) {
            $dir = dirname(__DIR__, 2).'/var';
            @mkdir($dir, 0o775, true);
            file_put_contents($dir.'/spotlight.svg', $svg);
            rename($image, $dir.'/spotlight.png');
            $image = $dir.'/spotlight.png';
        }

        try {
            return $this->poster->post(
                $io,
                new Post($spotlight->title, $spotlight->table, $spotlight->intro, images: $image !== null ? [$image] : []),
                $dryRun,
            );
        } finally {
            if (!$dryRun && $image !== null) {
                @unlink($image);
            }
        }
    }

    private function rasterize(string $svg): ?string
    {
        try {
            return $this->rasterizer->toPng($svg);
        } catch (RenderException) {
            return null;
        }
    }
}
