<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\MemberStatus;
use App\Domain\Review;
use App\Domain\ReviewSource;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\ReviewRepository;
use App\Persistence\RoundRepository;
use App\Reviewing\Candidate;
use App\Reviewing\Classifier;
use App\Reviewing\Exception\ClassifierException;
use App\Reviewing\MatchedBy;
use App\Reviewing\Matcher;
use App\Reviewing\Verdict;
use App\Reviewing\Watermark;
use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\LogReader;
use App\Telegram\Inbox\MessageKind;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reviews:import',
    description: 'Turn the captured Telegram messages into reviews.',
)]
final class ImportReviewsCommand extends Command
{
    private const string SKIP = '— пропустить';
    private const string NOT_A_REVIEW = '— не отзыв';

    public function __construct(
        private readonly LogReader $log,
        private readonly MemberRepository $members,
        private readonly FilmRepository $films,
        private readonly RoundRepository $rounds,
        private readonly ReviewRepository $reviews,
        private readonly Watermark $watermark,
        private readonly ?Classifier $classifier = null,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Show what would be imported without touching the database.')]
        bool $dryRun = false,
        #[Option(description: 'Walk every captured message again, not only the ones since the last run.')]
        bool $all = false,
    ): int {
        $messages = $this->log->all();
        if ($messages === []) {
            $io->success('The capture log is empty. Nothing to import.');

            return Command::SUCCESS;
        }

        $pinned = $this->linkAnnouncements($io, $messages, $dryRun);
        $written = $this->onlyWritten($messages);

        $titles = $this->filmTitles();
        $matcher = new Matcher(array_replace($this->rounds->announcements(), $pinned), $titles);
        $stored = $this->reviews->byTelegramMessage();
        $memberByTelegramId = $this->members->byTelegramId();
        $seen = $all ? 0 : $this->watermark->value();

        $candidates = [];
        foreach ($written as $message) {
            $known = $stored[$message->messageId] ?? null;
            if ($message->updateId <= $seen || ($known !== null && $known->body === $message->text)) {
                continue;
            }

            $candidates[] = $matcher->match($message);
        }

        $verdicts = $this->readMinds($io, $candidates, $titles);

        $imported = 0;
        $skipped = 0;
        foreach ($candidates as $candidate) {
            $message = $candidate->message;
            $known = $stored[$message->messageId] ?? null;

            $candidate = $this->resolveMember($io, $candidate, $memberByTelegramId, $dryRun);
            $candidate = $this->resolveFilm($io, $candidate, $titles, $verdicts[$message->messageId] ?? null);

            if (!$candidate->isComplete()) {
                ++$skipped;
                continue;
            }

            if (!$dryRun) {
                $this->reviews->save($this->toReview($candidate));
            }
            ++$imported;

            $io->writeln(sprintf(
                '  <info>%s</info> %s → %s',
                $known === null ? '+' : '~',
                $titles[$candidate->filmSlug] ?? $candidate->filmSlug,
                $candidate->memberUsername,
            ));
        }

        if (!$dryRun) {
            $latest = max(array_map(static fn (CapturedMessage $m): int => $m->updateId, $messages));
            $this->watermark->moveTo($latest);
            $io->writeln(sprintf('  <comment>просмотрено до #%d</comment> (%s)', $latest, $this->watermark->describe()));
        }

        $io->success(sprintf(
            '%s: отзывов %d, пропущено %d, анонсов привязано %d.',
            $dryRun ? 'Пробный прогон' : 'Готово',
            $imported,
            $skipped,
            count($pinned),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<CapturedMessage> $messages
     *
     * @return array<int, string>
     */
    private function linkAnnouncements(SymfonyStyle $io, array $messages, bool $dryRun): array
    {
        $linked = [];
        foreach ($messages as $message) {
            if ($message->kind !== MessageKind::Pin) {
                continue;
            }

            foreach ($message->urls as $url) {
                $slug = Matcher::slugIn($url);
                if ($slug === null || $this->films->find($slug) === null) {
                    continue;
                }

                if (!$dryRun) {
                    $this->rounds->linkAnnouncement($slug, $message->messageId);
                }
                $linked[$message->messageId] = $slug;
                $io->writeln(sprintf('  <comment>анонс</comment> %s → сообщение %d', $slug, $message->messageId));
                break;
            }
        }

        return $linked;
    }

    /**
     * @param list<CapturedMessage> $messages
     *
     * @return list<CapturedMessage>
     */
    private function onlyWritten(array $messages): array
    {
        return array_values(array_filter(
            $messages,
            static fn (CapturedMessage $m): bool => $m->kind !== MessageKind::Pin && $m->text !== '',
        ));
    }

    /**
     * @param array<int, string> $memberByTelegramId
     */
    private function resolveMember(SymfonyStyle $io, Candidate $candidate, array &$memberByTelegramId, bool $dryRun): Candidate
    {
        $authorId = $candidate->message->authorId;
        if ($authorId === null) {
            return $candidate;
        }

        if (isset($memberByTelegramId[$authorId])) {
            return $candidate->withMember($memberByTelegramId[$authorId]);
        }

        $io->section(sprintf(
            'Незнакомый автор: %s (id %d)',
            $candidate->message->authorUsername ?? 'без username',
            $authorId,
        ));
        $io->writeln($this->preview($candidate->message->text));

        $labels = $this->memberLabels();
        $choice = $io->choice('Кто это?', [...array_keys($labels), self::SKIP], self::SKIP);
        if ($choice === self::SKIP) {
            return $candidate;
        }

        $memberByTelegramId[$authorId] = $labels[$choice];
        if (!$dryRun) {
            $this->members->linkTelegram($labels[$choice], $authorId);
        }

        return $candidate->withMember($labels[$choice]);
    }

    /**
     * @param list<Candidate>       $candidates
     * @param array<string, string> $titles
     *
     * @return array<int, Verdict>
     */
    private function readMinds(SymfonyStyle $io, array $candidates, array $titles): array
    {
        $unresolved = array_values(array_map(
            static fn (Candidate $c): CapturedMessage => $c->message,
            array_filter($candidates, static fn (Candidate $c): bool => $c->filmSlug === null),
        ));

        if ($this->classifier === null || $unresolved === []) {
            return [];
        }

        $catalogue = [];
        foreach ($this->rounds->picksNewestFirst() as $slug => $when) {
            $catalogue[$slug] = ($titles[$slug] ?? $slug).' — '.$when;
        }

        $io->writeln(sprintf('  <comment>читаю %d сообщений…</comment>', count($unresolved)));

        try {
            return $this->classifier->classify($unresolved, $catalogue);
        } catch (ClassifierException $e) {
            $io->warning('Классификатор недоступен, разбираем вручную: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, string> $titles
     */
    private function resolveFilm(SymfonyStyle $io, Candidate $candidate, array $titles, ?Verdict $verdict): Candidate
    {
        if ($candidate->filmSlug !== null || $candidate->memberUsername === null) {
            return $candidate;
        }

        $io->section(sprintf('Отзыв от %s, фильм неизвестен', $candidate->memberUsername));
        $io->writeln($this->preview($candidate->message->text));

        $recent = [];
        foreach (array_slice($this->rounds->picksNewestFirst(), 0, 12, true) as $slug => $when) {
            $recent[$titles[$slug] ?? $slug] = $slug;
        }

        $default = self::NOT_A_REVIEW;
        $guess = $verdict?->isReview === true ? $verdict->filmSlug : null;
        if ($guess !== null && isset($titles[$guess])) {
            $default = $titles[$guess];
            $recent[$default] = $guess;
        }

        $choice = $io->choice('Какой фильм?', [...array_keys($recent), self::NOT_A_REVIEW], $default);
        if ($choice === self::NOT_A_REVIEW) {
            return $candidate;
        }

        return $candidate->withFilm($recent[$choice], MatchedBy::Model);
    }

    private function toReview(Candidate $candidate): Review
    {
        return new Review(
            (string) $candidate->filmSlug,
            (string) $candidate->memberUsername,
            $candidate->message->text,
            gmdate('Y-m-d', $candidate->message->date),
            ReviewSource::Telegram,
            $candidate->message->messageId,
        );
    }

    /**
     * @return array<string, string>
     */
    private function filmTitles(): array
    {
        $titles = [];
        foreach ($this->films->all() as $film) {
            $titles[$film->slug] = $film->title;
        }

        return $titles;
    }

    /**
     * @return array<string, string>
     */
    private function memberLabels(): array
    {
        $labels = [];
        foreach ($this->members->all() as $member) {
            if ($member->status === MemberStatus::Active) {
                $labels[sprintf('%s (@%s)', $member->displayName, $member->username)] = $member->username;
            }
        }

        return $labels;
    }

    private function preview(string $text): string
    {
        $flat = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return '  <comment>'.(mb_strlen($flat) > 160 ? mb_substr($flat, 0, 160).'…' : $flat).'</comment>';
    }
}
