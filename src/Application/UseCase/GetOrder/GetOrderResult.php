<?php

declare(strict_types=1);

namespace App\Application\UseCase\GetOrder;

final class GetOrderResult
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt
    ) {}
}

