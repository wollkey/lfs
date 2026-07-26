<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Domain\MemberStatus;
use App\Telegram\Messages;
use App\Telegram\Post;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Messages::class)]
#[CoversClass(Post::class)]
final class MessagesTest extends IntegrationTestCase
{
    public function testForCommandMapsKnownCommandsAndIgnoresTheRest(): void
    {
        $this->givenMembers('wollkey');
        $messages = $this->messages();

        self::assertInstanceOf(Post::class, $messages->forCommand('/members'));
        self::assertInstanceOf(Post::class, $messages->forCommand('/films'));
        self::assertNull($messages->forCommand('/unknown'));
        self::assertNull($messages->forCommand('hello there'));
        self::assertNull($messages->forCommand(''));
    }

    public function testForCommandStripsBotSuffixIgnoresCaseAndArguments(): void
    {
        $this->givenMembers('wollkey');
        $messages = $this->messages();

        self::assertEquals($messages->activeMembers(), $messages->forCommand('/members@LfsBot'));
        self::assertEquals($messages->activeMembers(), $messages->forCommand('/MEMBERS'));
        self::assertEquals($messages->activeMembers(), $messages->forCommand('  /members  extra args '));
    }

    public function testActiveMembersListsOnlyActiveMembers(): void
    {
        $this->givenMember('wollkey');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 8, 'justdanya' => 7]);

        $names = array_column($this->messages()->activeMembers()->table->rows, 1);

        self::assertContains('Wollkey', $names);
        self::assertNotContains('Justdanya', $names);
    }

    public function testWatchedFilmsOmitsFilmsNobodyRated(): void
    {
        $this->givenMembers('wollkey');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 8]);
        $this->givenFilmRatedBy('unseen', []);

        $titles = array_column($this->messages()->watchedFilms()->table->rows, 1);

        self::assertContains('Stalker', $titles);
        self::assertNotContains('Unseen', $titles);
    }

    public function testCurrentRoundStandingsCoversTheLatestRoundSortedByAverage(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1);
        $this->givenRound(2);
        $this->givenFilmRatedBy('low', ['wollkey' => 4, 'lenka' => 6]);
        $this->givenFilmRatedBy('high', ['wollkey' => 9, 'lenka' => 9]);
        $this->rounds->addFilm(2, 'low', 'wollkey', 1);
        $this->rounds->addFilm(2, 'high', 'lenka', 2);

        $post = $this->messages()->currentRoundStandings();

        self::assertStringContainsString('Круг 2', $post->title);
        self::assertSame(['High', 'Low'], array_column($post->table->rows, 1));
    }

    public function testCurrentRoundStandingsWithoutRounds(): void
    {
        $post = $this->messages()->currentRoundStandings();

        self::assertNull($post->table);
        self::assertSame('Круги ещё не начались.', $post->intro);
    }

    private function messages(): Messages
    {
        return new Messages($this->statistics(quorum: 1));
    }
}
