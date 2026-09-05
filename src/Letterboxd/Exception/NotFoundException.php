<?php

declare(strict_types=1);

namespace App\Letterboxd\Exception;

final class NotFoundException extends \RuntimeException implements LetterboxdException
{
}
