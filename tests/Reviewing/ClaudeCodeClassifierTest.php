<?php

declare(strict_types=1);

namespace App\Tests\Reviewing;

use App\Reviewing\ClaudeCodeClassifier;
use App\Reviewing\Exception\ClassifierException;
use App\Reviewing\Verdict;
use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\MessageKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClaudeCodeClassifier::class)]
#[CoversClass(Verdict::class)]
final class ClaudeCodeClassifierTest extends TestCase
{
    public function testReadsTheAnswerOutOfTheCliEnvelope(): void
    {
        $verdicts = ClaudeCodeClassifier::decode(json_encode([
            'type' => 'result',
            'result' => '[{"id": 321, "review": true, "film": "alien"}]',
        ], JSON_THROW_ON_ERROR));

        self::assertTrue($verdicts[321]->isReview);
        self::assertSame('alien', $verdicts[321]->filmSlug);
    }

    public function testReadsAnAnswerWrappedInAMarkdownFence(): void
    {
        $verdicts = ClaudeCodeClassifier::decode(json_encode([
            'result' => "```json\n[{\"id\": 7, \"review\": false, \"film\": null}]\n```",
        ], JSON_THROW_ON_ERROR));

        self::assertFalse($verdicts[7]->isReview);
        self::assertNull($verdicts[7]->filmSlug);
    }

    public function testReadsABarePlainArray(): void
    {
        $verdicts = ClaudeCodeClassifier::decode('[{"id": 9, "review": true, "film": "stalker"}]');

        self::assertSame('stalker', $verdicts[9]->filmSlug);
    }

    public function testRowsWithoutAnIdAreIgnored(): void
    {
        $verdicts = ClaudeCodeClassifier::decode('[{"review": true, "film": "stalker"}, {"id": 5, "review": true, "film": "alien"}]');

        self::assertSame([5], array_keys($verdicts));
    }

    public function testAnAnswerThatIsNotJsonIsAnError(): void
    {
        $this->expectException(ClassifierException::class);

        ClaudeCodeClassifier::decode('Конечно! Вот разбор ваших сообщений.');
    }

    public function testThePromptCarriesTheCatalogueAndTheMessages(): void
    {
        $prompt = ClaudeCodeClassifier::prompt(
            [$this->message(321, "Чужой\n\nНе зашло совсем.")],
            ['alien' => 'Alien — круг 6, 2026-09-08'],
        );

        self::assertStringContainsString('alien — Alien — круг 6, 2026-09-08', $prompt);
        self::assertStringContainsString('[321]', $prompt);
        self::assertStringContainsString('Не зашло совсем.', $prompt);
    }

    private function message(int $messageId, string $text): CapturedMessage
    {
        return new CapturedMessage(
            MessageKind::Message,
            1,
            -100,
            $messageId,
            1_789_041_600,
            4242,
            'christallisme',
            $text,
        );
    }
}
