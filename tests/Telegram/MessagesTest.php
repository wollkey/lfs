<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Domain\MemberStatus;
use App\Telegram\Cell;
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
        self::assertInstanceOf(Post::class, $messages->forCommand('/links'));
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

        $names = $this->column($this->messages()->activeMembers()->table->rows, 1);

        self::assertContains('Wollkey', $names);
        self::assertNotContains('Justdanya', $names);
    }

    public function testActiveMembersLinkNamesToLetterboxd(): void
    {
        $this->givenMember('wollkey');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 8]);

        $rows = $this->messages()->activeMembers()->table->rows;

        self::assertSame('https://letterboxd.com/wollkey/', $rows[0][1]->url);
    }

    public function testWatchedFilmsReturnsALinkToTheSite(): void
    {
        $post = $this->messages()->watchedFilms();

        self::assertNull($post->table);
        self::assertCount(1, $post->links);
        self::assertSame('https://lfs.wollkey.ru/films', $post->links[0]->url);
    }

    public function testLinksListsTheSitePages(): void
    {
        $urls = array_map(static fn ($cell): ?string => $cell->url, $this->messages()->links()->links);

        self::assertSame([
            'https://lfs.wollkey.ru/',
            'https://lfs.wollkey.ru/films',
            'https://lfs.wollkey.ru/rounds',
            'https://lfs.wollkey.ru/members',
        ], $urls);
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
        self::assertSame(['High', 'Low'], $this->column($post->table->rows, 1));
    }

    public function testCurrentRoundStandingsWithoutRounds(): void
    {
        $post = $this->messages()->currentRoundStandings();

        self::assertNull($post->table);
        self::assertSame('Круги ещё не начались.', $post->intro);
    }

    private function messages(): Messages
    {
        return new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru');
    }

    /**
     * @param list<list<Cell>> $rows
     *
     * @return list<string>
     */
    private function column(array $rows, int $index): array
    {
        return array_map(static fn (array $row): string => $row[$index]->text, $rows);
    }
}
