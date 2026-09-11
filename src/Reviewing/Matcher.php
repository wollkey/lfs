<?php

declare(strict_types=1);

namespace App\Reviewing;

use App\Telegram\Inbox\CapturedMessage;

final readonly class Matcher
{
    /**
     * @param array<int, string>    $filmByAnnouncement
     * @param array<string, string> $knownSlugs
     */
    public function __construct(
        private array $filmByAnnouncement,
        private array $knownSlugs,
    ) {
    }

    public function match(CapturedMessage $message): Candidate
    {
        $announced = $this->fromAnnouncement($message);
        if ($announced !== null) {
            return new Candidate($message, $announced, null, MatchedBy::Announcement);
        }

        $linked = $this->fromLinks([...$message->urls, ...$message->replyToUrls]);
        if ($linked !== null) {
            return new Candidate($message, $linked, null, MatchedBy::Link);
        }

        return new Candidate($message, null, null, MatchedBy::Unknown);
    }

    public static function slugIn(string $url): ?string
    {
        if (preg_match('~letterboxd\.com/film/([^/?#\s]+)~i', $url, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function fromAnnouncement(CapturedMessage $message): ?string
    {
        if ($message->replyToMessageId === null) {
            return null;
        }

        return $this->filmByAnnouncement[$message->replyToMessageId] ?? null;
    }

    /**
     * @param list<string> $urls
     */
    private function fromLinks(array $urls): ?string
    {
        foreach ($urls as $url) {
            $slug = self::slugIn($url);
            if ($slug !== null && isset($this->knownSlugs[$slug])) {
                return $slug;
            }
        }

        return null;
    }
}
