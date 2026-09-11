<?php

declare(strict_types=1);

namespace App\Reviewing;

use App\Reviewing\Exception\ClassifierException;
use App\Telegram\Inbox\CapturedMessage;

interface Classifier
{
    /**
     * @param list<CapturedMessage> $messages
     * @param array<string, string> $films    slug => human label
     *
     * @return array<int, Verdict> keyed by Telegram message id
     *
     * @throws ClassifierException
     */
    public function classify(array $messages, array $films): array;
}
