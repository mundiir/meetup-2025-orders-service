<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Port\RiskChecker;
use App\Domain\Entity\Order;

final class SimpleRiskChecker implements RiskChecker
{
    public function isAllowed(Order $order, int $chargedAmountCents, string $chargedCurrency): bool
    {
        // Reject if final charge > $1000 equivalent
        if (strtoupper($chargedCurrency) === 'USD' && $chargedAmountCents > 100000) {
            return false;
        }
        // Very simple rule: reject UAH charges above ~15000 UAH equivalent threshold
        if (strtoupper($chargedCurrency) === 'UAH' && $chargedAmountCents > 1500000) {
            return false;
        }
        return true;
    }
}

