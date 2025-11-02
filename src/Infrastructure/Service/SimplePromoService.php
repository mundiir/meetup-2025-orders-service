<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Port\PromoService;

final class SimplePromoService implements PromoService
{
    public function getDiscountPercent(?string $promoCode, string $currency): int
    {
        $code = $promoCode ? strtoupper(trim($promoCode)) : '';
        return match ($code) {
            'PROMO10' => 10,
            'PROMO25' => 25,
            'FREE100' => 100,
            default => 0,
        };
    }
}

