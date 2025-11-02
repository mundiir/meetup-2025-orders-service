<?php

declare(strict_types=1);

namespace App\Application\Port;

interface FxConverter
{
    public function convert(int $amountCents, string $fromCurrency, string $toCurrency): int;
}

