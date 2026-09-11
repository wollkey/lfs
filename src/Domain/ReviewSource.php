<?php

declare(strict_types=1);

namespace App\Domain;

enum ReviewSource: string
{
    case Manual = 'manual';
    case Telegram = 'telegram';
}
