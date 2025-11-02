<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\OrderId;

final class OrderCreated
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly int $chargedAmountCents,
        public readonly string $chargedCurrency,
        public readonly int $appliedDiscountPercent,
        public readonly string $transactionId
    ) {}
}
