<?php

declare(strict_types=1);

namespace App\Tests\Telegram\Inbox;

use App\Telegram\Inbox\Capture;
use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\MessageKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Capture::class)]
#[CoversClass(CapturedMessage::class)]
final class CaptureTest extends TestCase
{
    private const int CHAT = -1001234567890;

    private const string REVIEW = 'Повторный просмотр спустя несколько лет дался намного легче, '
        .'хотя финал я всё ещё считаю затянутым и не до конца честным по отношению к зрителю.';

    public function testKeepsALongMessage(): void
    {
        $captured = $this->capture($this->message(self::REVIEW));

        self::assertNotNull($captured);
        self::assertSame(MessageKind::Message, $captured->kind);
        self::assertSame(self::REVIEW, $captured->text);
        self::assertSame(4242, $captured->authorId);
        self::assertSame('christallisme', $captured->authorUsername);
    }

    public function testDropsChatter(): void
    {
        self::assertNull($this->capture($this->message('Согласен')));
    }

    public function testKeepsAShortMessageCarryingATag(): void
    {
        $captured = $this->capture($this->message('#рецензия Коротко: не зашло.'));

        self::assertNotNull($captured);
        self::assertSame(['рецензия'], $captured->tags);
    }

    public function testDropsOtherChats(): void
    {
        $message = $this->message(self::REVIEW);
        $message['chat']['id'] = -1009999999999;

        self::assertNull($this->capture($message));
    }

    public function testDropsUpdatesWithoutAMessage(): void
    {
        self::assertNull(new Capture(self::CHAT)->fromUpdate(['update_id' => 7]));
    }

    public function testReadsAnEditedMessage(): void
    {
        $captured = new Capture(self::CHAT)->fromUpdate([
            'update_id' => 7,
            'edited_message' => $this->message(self::REVIEW),
        ]);

        self::assertNotNull($captured);
        self::assertSame(MessageKind::Edited, $captured->kind);
    }

    public function testKeepsTheRepliedToAnnouncementWithItsLinks(): void
    {
        $message = $this->message(self::REVIEW);
        $message['reply_to_message'] = [
            'message_id' => 100,
            'date' => 1_789_041_600,
            'from' => ['id' => 1, 'username' => 'wollkey'],
            'text' => 'На этой неделе смотрим «Сталкер» https://letterboxd.com/film/stalker/',
        ];

        $captured = $this->capture($message);

        self::assertNotNull($captured);
        self::assertSame(100, $captured->replyToMessageId);
        self::assertSame(1, $captured->replyToAuthorId);
        self::assertSame(['https://letterboxd.com/film/stalker/'], $captured->replyToUrls);
    }

    public function testCapturesAPinnedAnnouncementWhateverItsLength(): void
    {
        $captured = new Capture(self::CHAT)->fromUpdate([
            'update_id' => 7,
            'message' => [
                'message_id' => 501,
                'date' => 1_789_041_600,
                'chat' => ['id' => self::CHAT],
                'from' => ['id' => 777, 'username' => 'GroupAnonymousBot'],
                'pinned_message' => [
                    'message_id' => 500,
                    'date' => 1_789_038_000,
                    'from' => ['id' => 1, 'username' => 'wollkey'],
                    'text' => 'Смотрим «Сталкер»',
                    'entities' => [
                        ['type' => 'text_link', 'offset' => 8, 'length' => 9, 'url' => 'https://letterboxd.com/film/stalker/'],
                    ],
                ],
            ],
        ]);

        self::assertNotNull($captured);
        self::assertSame(MessageKind::Pin, $captured->kind);
        self::assertSame(500, $captured->messageId);
        self::assertSame(1, $captured->authorId);
        self::assertSame(['https://letterboxd.com/film/stalker/'], $captured->urls);
    }

    public function testCollectsLinksFromTheMessageItself(): void
    {
        $captured = $this->capture($this->message(self::REVIEW.' https://letterboxd.com/film/stalker/.'));

        self::assertNotNull($captured);
        self::assertSame(['https://letterboxd.com/film/stalker/'], $captured->urls);
    }

    /**
     * @param array<mixed> $message
     */
    private function capture(array $message): ?CapturedMessage
    {
        return new Capture(self::CHAT)->fromUpdate(['update_id' => 7, 'message' => $message]);
    }

    /**
     * @return array<mixed>
     */
    private function message(string $text): array
    {
        return [
            'message_id' => 321,
            'date' => 1_789_041_600,
            'chat' => ['id' => self::CHAT],
            'from' => ['id' => 4242, 'username' => 'christallisme'],
            'text' => $text,
        ];
    }
}
