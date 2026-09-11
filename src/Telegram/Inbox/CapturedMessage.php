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
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            MessageKind::from((string) $row['kind']),
            (int) $row['updateId'],
            (int) $row['chatId'],
            (int) $row['messageId'],
            (int) $row['date'],
            isset($row['authorId']) ? (int) $row['authorId'] : null,
            isset($row['authorUsername']) ? (string) $row['authorUsername'] : null,
            (string) $row['text'],
            self::strings($row['urls'] ?? []),
            self::strings($row['tags'] ?? []),
            isset($row['replyToMessageId']) ? (int) $row['replyToMessageId'] : null,
            isset($row['replyToAuthorId']) ? (int) $row['replyToAuthorId'] : null,
            isset($row['replyToText']) ? (string) $row['replyToText'] : null,
            self::strings($row['replyToUrls'] ?? []),
        );
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

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($value, is_scalar(...))));
    }
}
