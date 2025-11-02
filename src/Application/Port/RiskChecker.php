<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Entity\Order;

interface RiskChecker
{
    public function isAllowed(Order $order, int $chargedAmountCents, string $chargedCurrency): bool;
}

