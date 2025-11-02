<?php

declare(strict_types=1);

namespace App\Application\Port;

interface PromoService
{
    /**
     * Returns discount percent [0..100] for a given promo code and currency.
     */
    public function getDiscountPercent(?string $promoCode, string $currency): int;
}

