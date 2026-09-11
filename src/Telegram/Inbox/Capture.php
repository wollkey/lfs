<?php

declare(strict_types=1);

namespace App\Telegram\Inbox;

final readonly class Capture
{
    public const int MIN_LENGTH = 100;

    public function __construct(
        private ?int $chatId = null,
        private int $minLength = self::MIN_LENGTH,
    ) {
    }

    /**
     * @param array<mixed> $update
     */
    public function fromUpdate(array $update): ?CapturedMessage
    {
        $updateId = $update['update_id'] ?? null;
        if (!is_int($updateId)) {
            return null;
        }

        $kind = MessageKind::Message;
        $message = $this->section($update, 'message');
        if ($message === null) {
            $message = $this->section($update, 'edited_message');
            $kind = MessageKind::Edited;
        }
        if ($message === null) {
            return null;
        }

        $chat = $this->section($message, 'chat');
        $chatId = $chat === null ? null : $this->integer($chat, 'id');
        if ($chatId === null || ($this->chatId !== null && $chatId !== $this->chatId)) {
            return null;
        }

        $pinned = $this->section($message, 'pinned_message');
        if ($pinned !== null) {
            return $this->announcement($updateId, $chatId, $pinned);
        }

        $messageId = $this->integer($message, 'message_id');
        $date = $this->integer($message, 'date');
        if ($messageId === null || $date === null) {
            return null;
        }

        $text = $this->text($message);
        $tags = $this->tags($text);
        if ($tags === [] && mb_strlen($text) < $this->minLength) {
            return null;
        }

        $reply = $this->section($message, 'reply_to_message');
        $replyText = $reply === null ? '' : $this->text($reply);

        return new CapturedMessage(
            $kind,
            $updateId,
            $chatId,
            $messageId,
            $date,
            $this->authorId($message),
            $this->authorUsername($message),
            $text,
            $this->urls($message, $text),
            $tags,
            $reply === null ? null : $this->integer($reply, 'message_id'),
            $reply === null ? null : $this->authorId($reply),
            $replyText === '' ? null : $replyText,
            $reply === null ? [] : $this->urls($reply, $replyText),
        );
    }

    /**
     * @param array<mixed> $pinned
     */
    private function announcement(int $updateId, int $chatId, array $pinned): ?CapturedMessage
    {
        $messageId = $this->integer($pinned, 'message_id');
        $date = $this->integer($pinned, 'date');
        if ($messageId === null || $date === null) {
            return null;
        }

        $text = $this->text($pinned);

        return new CapturedMessage(
            MessageKind::Pin,
            $updateId,
            $chatId,
            $messageId,
            $date,
            $this->authorId($pinned),
            $this->authorUsername($pinned),
            $text,
            $this->urls($pinned, $text),
            $this->tags($text),
        );
    }

    /**
     * @param array<mixed> $message
     */
    private function text(array $message): string
    {
        $text = $message['text'] ?? $message['caption'] ?? null;

        return is_string($text) ? trim($text) : '';
    }

    /**
     * @return list<string>
     */
    private function tags(string $text): array
    {
        if (preg_match_all('/(?<!\S)#([\p{L}\p{N}_]+)/u', $text, $matches) < 1) {
            return [];
        }

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }

    /**
     * @param array<mixed> $message
     *
     * @return list<string>
     */
    private function urls(array $message, string $text): array
    {
        $entities = $message['entities'] ?? $message['caption_entities'] ?? null;
        $urls = [];

        foreach (is_array($entities) ? $entities : [] as $entity) {
            $url = is_array($entity) ? ($entity['url'] ?? null) : null;
            if (is_string($url)) {
                $urls[] = $url;
            }
        }

        if (preg_match_all('~https?://\S+~u', $text, $matches) > 0) {
            foreach ($matches[0] as $url) {
                $urls[] = rtrim($url, '.,;)');
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array<mixed> $message
     */
    private function authorId(array $message): ?int
    {
        $from = $this->section($message, 'from');

        return $from === null ? null : $this->integer($from, 'id');
    }

    /**
     * @param array<mixed> $message
     */
    private function authorUsername(array $message): ?string
    {
        $from = $this->section($message, 'from');
        $username = $from === null ? null : ($from['username'] ?? null);

        return is_string($username) && $username !== '' ? $username : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    private function section(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function integer(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
