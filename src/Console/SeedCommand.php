<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Film;
use App\Domain\Member;
use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Parser\ActivityParser;
use App\Letterboxd\Parser\FriendsRatingsParser;
use App\Letterboxd\Parser\ListParser;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\RatingRepository;
use App\Persistence\RoundRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seed',
    description: 'Parse local HTML and upsert films, ratings and round structure into the DB.',
)]
final class SeedCommand extends Command
{
    private const int FIRST_ROUND_SIZE = 20;
    private const int ROUND_SIZE = 10;

    public function __construct(
        private readonly ListParser $listParser,
        private readonly FriendsRatingsParser $friendsParser,
        private readonly ActivityParser $activityParser,
        private readonly FilmRepository $films,
        private readonly MemberRepository $members,
        private readonly RatingRepository $ratings,
        private readonly RoundRepository $rounds,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with list.html and friends/{slug}.html')]
        string $htmlDir = 'data',
    ): int {
        $listFile = "{$htmlDir}/list.html";
        if (!is_file($listFile)) {
            $io->error("List HTML not found: {$listFile}");

            return Command::INVALID;
        }

        $listHtml = (string) file_get_contents($listFile);
        $films = $this->listParser->parse($listHtml);

        $this->seedFilms($films);
        $this->seedRounds($films);

        $written = [
            ...$this->seedRatings($io, $htmlDir, $listHtml, $films),
            ...$this->seedActivityRatings($io, $htmlDir, $films),
        ];

        $io->success(sprintf(
            'Seeded %d film(s) and %d rating(s).',
            count($films),
            count(array_unique($written)),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<ParsedFilm> $films
     */
    private function seedFilms(array $films): void
    {
        foreach ($films as $film) {
            $this->films->save(new Film($film->slug, $film->title));
        }
    }

    /**
     * Rebuilds round structure from list order (20/10/10). Positions are upserted;
     * picked_by is preserved on existing rows and left null on new ones.
     *
     * @param list<ParsedFilm> $films
     */
    private function seedRounds(array $films): void
    {
        $round = 1;
        $positionInRound = 0;
        $roundCapacity = self::FIRST_ROUND_SIZE;

        foreach ($films as $film) {
            if ($positionInRound === $roundCapacity) {
                ++$round;
                $positionInRound = 0;
                $roundCapacity = self::ROUND_SIZE;
            }
            ++$positionInRound;

            $this->rounds->ensure($round);
            $this->rounds->syncFilm($round, $film->slug, $positionInRound);
        }
    }

    /**
     * Loads owner ratings from the list plus friend ratings from friends/{slug}.html.
     * Returns the "slug|username" key of each rating written, so the caller can
     * count distinct ratings across sources.
     *
     * @param list<ParsedFilm> $films
     *
     * @return list<string>
     */
    private function seedRatings(SymfonyStyle $io, string $htmlDir, string $listHtml, array $films): array
    {
        $written = [];

        foreach ($this->listParser->ownerRatings($listHtml) as $slug => $rating) {
            $this->ratings->setRating($slug, $rating->username, $rating->rating);
            $written[] = "{$slug}|{$rating->username}";
        }

        foreach ($films as $film) {
            $friendsFile = "{$htmlDir}/friends/{$film->slug}.html";
            if (!is_file($friendsFile)) {
                $io->warning(sprintf('No friends page for "%s".', $film->slug));
                continue;
            }

            $friendRatings = $this->friendsParser->parse((string) file_get_contents($friendsFile));
            foreach ($friendRatings as $rating) {
                $this->ratings->setRating($film->slug, $rating->username, $rating->rating);
                $written[] = "{$film->slug}|{$rating->username}";
            }
        }

        return $written;
    }

    /**
     * Loads member ratings from friends_activity/{username}.html fragments
     * (saved from /ajax/activity-pagination/{username}/). Optional: the
     * directory may be absent. Only ratings for club films (present in the list)
     * are written — activity lists every film a member has rated, most of which
     * are not club films. Returns the "slug|username" key of each rating written,
     * so the caller can count distinct ratings across sources.
     *
     * @param list<ParsedFilm> $films
     *
     * @return list<string>
     */
    private function seedActivityRatings(SymfonyStyle $io, string $htmlDir, array $films): array
    {
        $activityDir = "{$htmlDir}/friends_activity";
        if (!is_dir($activityDir)) {
            return [];
        }

        $known = array_flip(array_map(static fn (ParsedFilm $film) => $film->slug, $films));
        $members = array_flip(array_map(static fn (Member $member) => $member->username, $this->members->all()));

        $written = [];
        foreach (glob("{$activityDir}/*.html") as $file) {
            $username = basename($file, '.html');
            if (!isset($members[$username])) {
                $io->warning(sprintf('Skipping "%s": not a known member.', basename($file)));
                continue;
            }

            $ratings = $this->activityParser->parse((string) file_get_contents($file));
            foreach ($ratings as $slug => $score) {
                if (!isset($known[$slug])) {
                    continue;
                }

                $this->ratings->setRating($slug, $username, $score);
                $written[] = "{$slug}|{$username}";
            }
        }

        if ($written === []) {
            $io->note(sprintf('No club-film ratings found in "%s".', $activityDir));
        }

        return $written;
    }
}
