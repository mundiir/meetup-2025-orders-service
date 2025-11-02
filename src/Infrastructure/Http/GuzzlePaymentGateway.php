<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\PaymentGateway;

/**
 * Stub implementation that simulates a remote payment gateway.
 * Named GuzzlePaymentGateway to match the adapter slot; no external deps required.
 */
final class GuzzlePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $baseUri = null,
        private readonly float $latencyMs = 20.0
    ) {}

    public function charge(int $amountCents, string $currency): string
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('charge amount must be > 0');
        }
        // Simulate small latency
        usleep((int)($this->latencyMs * 1000));
        // Always succeed in this demo and return a pseudo transaction id
        return 'tx_' . bin2hex(random_bytes(6));
    }
}
