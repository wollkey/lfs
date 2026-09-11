<?php

declare(strict_types=1);

namespace App\Telegram\Inbox;

enum MessageKind: string
{
    case Message = 'message';
    case Edited = 'edited';
    case Pin = 'pin';
}
