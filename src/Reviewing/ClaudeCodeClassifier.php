<?php

declare(strict_types=1);

namespace App\Reviewing;

use App\Reviewing\Exception\ClassifierException;
use App\Telegram\Inbox\CapturedMessage;

final readonly class ClaudeCodeClassifier implements Classifier
{
    public const int BATCH = 20;

    public function __construct(
        private string $binary = 'claude',
        private int $batch = self::BATCH,
    ) {
    }

    public function classify(array $messages, array $films): array
    {
        $verdicts = [];
        foreach (array_chunk($messages, $this->batch) as $chunk) {
            $verdicts += self::decode($this->run(self::prompt($chunk, $films)));
        }

        return $verdicts;
    }

    /**
     * @param list<CapturedMessage> $messages
     * @param array<string, string> $films
     */
    public static function prompt(array $messages, array $films): string
    {
        $catalogue = [];
        foreach ($films as $slug => $label) {
            $catalogue[] = $slug.' — '.$label;
        }
        $catalogueText = implode("\n", $catalogue);

        $blocks = [];
        foreach ($messages as $message) {
            $blocks[] = sprintf("[%d]\n%s", $message->messageId, $message->text);
        }
        $messagesText = implode("\n---\n", $blocks);

        return <<<PROMPT
            Ты разбираешь сообщения из чата киноклуба. Для каждого сообщения реши,
            отзыв ли это о фильме, и если да — о каком фильме из каталога.

            Отзыв — это личное впечатление о фильме: понравилось или нет, мысли о нём,
            оценка. Отзыв бывает и в одно предложение. Организационные сообщения, шутки,
            обсуждение расписания и реплики в споре отзывами не считаются.

            Каталог хранит названия на языке оригинала, а участники пишут русские
            прокатные: «Чужой» — это Alien, «Космическая одиссея» — 2001: A Space
            Odyssey, «Хороший, плохой, злой» — The Good, the Bad and the Ugly.
            Переводи названия сам и сопоставляй по смыслу, а не по буквам. Часто
            название стоит отдельной первой строкой, бывает в косвенном падеже
            («на Барри Линдона», «про Космическую одиссею») или заменено отсылкой
            к сюжету. Ставь film: null, только если фильма действительно нет
            в каталоге или о нём вообще нельзя догадаться.

            Поле title_line — правда, если первая строка сообщения это только название
            фильма и ничего больше (её потом отрежут). Если название вплетено в фразу
            («„Сталкер“, поставила ему 2.5/5»), то ложь.

            Каталог фильмов (слаг — название — когда смотрели):
            {$catalogueText}

            Сообщения:
            {$messagesText}

            Ответь одним JSON-массивом и ничем больше:
            [{"id": 123, "review": true, "film": "alien", "title_line": true}]
            PROMPT;
    }

    /**
     * @return array<int, Verdict>
     */
    public static function decode(string $raw): array
    {
        $payload = json_decode(trim($raw), true);

        if (is_array($payload) && is_string($payload['result'] ?? null)) {
            $payload = json_decode(self::unfence($payload['result']), true);
        }

        if (!is_array($payload)) {
            throw new ClassifierException('The classifier did not answer with JSON: '.mb_substr(trim($raw), 0, 200));
        }

        $verdicts = [];
        foreach ($payload as $row) {
            if (!is_array($row) || !is_int($row['id'] ?? null)) {
                continue;
            }

            $film = $row['film'] ?? null;
            $verdicts[$row['id']] = new Verdict(
                (bool) ($row['review'] ?? false),
                is_string($film) && $film !== '' ? $film : null,
                (bool) ($row['title_line'] ?? false),
            );
        }

        return $verdicts;
    }

    private function run(string $prompt): string
    {
        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $process = proc_open([$this->binary, '-p', '--output-format', 'json'], $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new ClassifierException("Cannot start {$this->binary}.");
        }

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);

        $answer = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($process);
        if ($status !== 0) {
            throw new ClassifierException(sprintf('%s exited with %d: %s', $this->binary, $status, trim($error)));
        }

        return $answer;
    }

    private static function unfence(string $text): string
    {
        $trimmed = trim($text);
        if (!str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $withoutOpening = preg_replace('~^```[a-z]*\s*~i', '', $trimmed) ?? $trimmed;

        return trim(preg_replace('~```\s*$~', '', $withoutOpening) ?? $withoutOpening);
    }
}
