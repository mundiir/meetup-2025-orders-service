<?php

declare(strict_types=1);

namespace App\Application\Port;

interface PaymentGateway
{
    /**
     * Attempts to charge and returns a payment transaction ID on success.
     * Should throw on failure.
     */
    public function charge(int $amountCents, string $currency): string;
}
