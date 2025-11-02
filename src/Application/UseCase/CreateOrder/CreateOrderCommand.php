<?php

declare(strict_types=1);

namespace App\Application\UseCase\CreateOrder;

final class CreateOrderCommand
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $promoCode = null
    ) {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('amountCents must be > 0');
        }
        if ($currency === '') {
            throw new \InvalidArgumentException('currency cannot be empty');
        }
    }
}
