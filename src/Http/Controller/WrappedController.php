<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\NotFound;
use App\Statistics\Wrapped\Wrapped;
use App\Statistics\Wrapped\WrappedYear;

final readonly class WrappedController
{
    public function __construct(
        private Wrapped $wrapped,
    ) {
    }

    public function __invoke(string $year): WrappedYear
    {
        return $this->wrapped->year((int) $year) ?? throw new NotFound("No films picked in {$year}.");
    }
}
