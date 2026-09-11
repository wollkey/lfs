<?php

declare(strict_types=1);

namespace App\Tests\Reviewing;

use App\Reviewing\Candidate;
use App\Reviewing\MatchedBy;
use App\Reviewing\Matcher;
use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\MessageKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Matcher::class)]
#[CoversClass(Candidate::class)]
final class MatcherTest extends TestCase
{
    public function testAReplyToTheAnnouncementNamesTheFilm(): void
    {
        $candidate = $this->matcher()->match($this->message(replyToMessageId: 500));

        self::assertSame('stalker', $candidate->filmSlug);
        self::assertSame(MatchedBy::Announcement, $candidate->matchedBy);
        self::assertTrue($candidate->withMember('christallisme')->isComplete());
    }

    public function testALetterboxdLinkNamesTheFilm(): void
    {
        $candidate = $this->matcher()->match(
            $this->message(urls: ['https://letterboxd.com/film/Stalker/']),
        );

        self::assertSame('stalker', $candidate->filmSlug);
        self::assertSame(MatchedBy::Link, $candidate->matchedBy);
    }

    public function testALinkToAFilmWeDoNotKnowIsNotAMatch(): void
    {
        $candidate = $this->matcher()->match(
            $this->message(urls: ['https://letterboxd.com/film/some-other-film/']),
        );

        self::assertNull($candidate->filmSlug);
        self::assertSame(MatchedBy::Unknown, $candidate->matchedBy);
    }

    public function testTheAuthorIsLeftToTheCaller(): void
    {
        $candidate = $this->matcher()->match($this->message(replyToMessageId: 500));

        self::assertNull($candidate->memberUsername);
        self::assertFalse($candidate->isComplete());
    }

    public function testAPlainMessageMatchesNothing(): void
    {
        $candidate = $this->matcher()->match($this->message());

        self::assertNull($candidate->filmSlug);
        self::assertSame(MatchedBy::Unknown, $candidate->matchedBy);
    }

    private function matcher(): Matcher
    {
        return new Matcher([500 => 'stalker'], ['stalker' => 'Stalker', 'solaris' => 'Solaris']);
    }

    /**
     * @param list<string> $urls
     */
    private function message(int $authorId = 4242, ?int $replyToMessageId = null, array $urls = []): CapturedMessage
    {
        return new CapturedMessage(
            MessageKind::Message,
            1,
            -100,
            321,
            1_789_041_600,
            $authorId,
            'christallisme',
            'Повторный просмотр дался легче.',
            $urls,
            [],
            $replyToMessageId,
        );
    }
}
