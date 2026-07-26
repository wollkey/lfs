<?php

declare(strict_types=1);

namespace App\Telegram\Exception;

class ApiException extends \RuntimeException implements TelegramException
{
}
