<?php

declare(strict_types=1);

namespace App\Reviewing;

enum MatchedBy: string
{
    case Announcement = 'announcement';
    case Link = 'link';
    case Unknown = 'unknown';
}
