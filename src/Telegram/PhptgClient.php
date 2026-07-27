<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Exception\ApiException;
use Phptg\BotApi\FailResult;
use Phptg\BotApi\TelegramBotApi;
use Phptg\BotApi\TelegramRuntimeException;
use Phptg\BotApi\Type\InputFile;
use Phptg\BotApi\Type\InputRichBlock;
use Phptg\BotApi\Type\InputRichBlockList;
use Phptg\BotApi\Type\InputRichBlockListItem;
use Phptg\BotApi\Type\InputRichBlockParagraph;
use Phptg\BotApi\Type\InputRichBlockSectionHeading;
use Phptg\BotApi\Type\InputRichBlockTable;
use Phptg\BotApi\Type\InputRichMessage;
use Phptg\BotApi\Type\RichBlockTableCell;
use Phptg\BotApi\Type\RichText;
use Phptg\BotApi\Type\RichTextUrl;

final readonly class PhptgClient implements TelegramClient
{
    public function __construct(
        private TelegramBotApi $api,
    ) {
    }

    public function send(string $chatId, Post $post): void
    {
        try {
            $result = $this->api->sendRichMessage($chatId, new InputRichMessage(blocks: $this->blocks($post)));
        } catch (TelegramRuntimeException $e) {
            throw new ApiException('Telegram sendRichMessage failed.', previous: $e);
        }

        if ($result instanceof FailResult) {
            throw new ApiException($this->describe('sendRichMessage', $result));
        }
    }

    public function sendPhoto(string $chatId, string $imagePath, string $caption): void
    {
        try {
            $result = $this->api->sendPhoto(chatId: $chatId, photo: new InputFile($imagePath), caption: $caption);
        } catch (TelegramRuntimeException $e) {
            throw new ApiException('Telegram sendPhoto failed.', previous: $e);
        }

        if ($result instanceof FailResult) {
            throw new ApiException($this->describe('sendPhoto', $result));
        }
    }

    public function setWebhook(string $url, #[\SensitiveParameter] string $secretToken): void
    {
        try {
            $result = $this->api->setWebhook(url: $url, secretToken: $secretToken);
        } catch (TelegramRuntimeException $e) {
            throw new ApiException('Telegram setWebhook failed.', previous: $e);
        }

        if ($result instanceof FailResult) {
            throw new ApiException($this->describe('setWebhook', $result));
        }
    }

    /**
     * @return list<InputRichBlock>
     */
    private function blocks(Post $post): array
    {
        $blocks = [new InputRichBlockSectionHeading($post->title, 1)];

        if ($post->intro !== null) {
            $blocks[] = new InputRichBlockParagraph($post->intro);
        }

        if ($post->table !== null) {
            $blocks[] = $this->table($post->table);
        }

        if ($post->links !== []) {
            $blocks[] = $this->linkList($post->links);
        }

        return $blocks;
    }

    private function table(Table $table): InputRichBlockTable
    {
        $header = [];
        foreach ($table->headers as $label) {
            $header[] = new RichBlockTableCell('left', 'middle', $label, isHeader: true);
        }

        $cells = [$header];
        foreach ($table->rows as $row) {
            $line = [];
            foreach ($row as $cell) {
                $line[] = new RichBlockTableCell('left', 'middle', $this->richText($cell));
            }
            $cells[] = $line;
        }

        return new InputRichBlockTable($cells, isBordered: true, isStriped: true);
    }

    /**
     * @param list<Cell> $links
     */
    private function linkList(array $links): InputRichBlockList
    {
        $items = [];
        foreach ($links as $link) {
            $items[] = new InputRichBlockListItem([new InputRichBlockParagraph($this->richText($link))]);
        }

        return new InputRichBlockList($items);
    }

    private function richText(Cell $cell): string|RichText
    {
        return $cell->url !== null ? new RichTextUrl($cell->text, $cell->url) : $cell->text;
    }

    private function describe(string $method, FailResult $result): string
    {
        return sprintf(
            'Telegram %s failed: %s (error %d).',
            $method,
            $result->description ?? 'unknown error',
            $result->errorCode ?? 0,
        );
    }
}
