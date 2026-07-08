<?php

declare(strict_types=1);

namespace App\Domain;

enum MemberStatus: string
{
    case Active = 'active';
    case Former = 'former';
}
