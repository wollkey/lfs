<?php

declare(strict_types=1);

namespace App\Telegram\Inbox;

final readonly class CapturedMessage
{
    /**
     * @param list<string> $urls
     * @param list<string> $tags
     * @param list<string> $replyToUrls
     */
    public function __construct(
        public MessageKind $kind,
        public int $updateId,
        public int $chatId,
        public int $messageId,
        public int $date,
        public ?int $authorId,
        public ?string $authorUsername,
        public string $text,
        public array $urls = [],
        public array $tags = [],
        public ?int $replyToMessageId = null,
        public ?int $replyToAuthorId = null,
        public ?string $replyToText = null,
        public array $replyToUrls = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'updateId' => $this->updateId,
            'chatId' => $this->chatId,
            'messageId' => $this->messageId,
            'date' => $this->date,
            'authorId' => $this->authorId,
            'authorUsername' => $this->authorUsername,
            'text' => $this->text,
            'urls' => $this->urls,
            'tags' => $this->tags,
            'replyToMessageId' => $this->replyToMessageId,
            'replyToAuthorId' => $this->replyToAuthorId,
            'replyToText' => $this->replyToText,
            'replyToUrls' => $this->replyToUrls,
        ];
    }
}
