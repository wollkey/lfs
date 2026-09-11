<?php

declare(strict_types=1);

namespace App\Tests\Reviewing;

use App\Reviewing\Classifier;
use App\Reviewing\Verdict;

final class FixedVerdicts implements Classifier
{
    /**
     * @var array<string, string>
     */
    public array $catalogue = [];

    /**
     * @param array<int, Verdict> $verdicts
     */
    public function __construct(
        private readonly array $verdicts,
    ) {
    }

    public function classify(array $messages, array $films): array
    {
        $this->catalogue = $films;

        return $this->verdicts;
    }
}
