<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Exception\TransientPaymentException;
use App\Application\Port\PaymentGateway;

/**
 * HTTP adapter to the Payment microservice; falls back to a local stub if no baseUri is set.
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

        // If no base URI configured, simulate a gateway for local/demo runs
        if ($this->baseUri === null || $this->baseUri === '') {
            usleep((int)($this->latencyMs * 1000));
            return 'tx_' . bin2hex(random_bytes(6));
        }

        // Remote call to Payment Service
        $endpoint = rtrim($this->baseUri, '/') . '/payments/charge';
        $payload = json_encode([
            'amountCents' => $amountCents,
            'currency' => strtoupper($currency),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                'content' => $payload,
                'timeout' => 2.0, // seconds
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($endpoint, false, $ctx);
        $status = $this->extractStatusCode($http_response_header ?? []);
        if ($status !== 201) {
            throw new TransientPaymentException('Payment service responded with status ' . ($status ?? 'unknown'));
        }
        $data = json_decode($resp ?: '', true);
        if (!is_array($data) || !isset($data['transactionId']) || !is_string($data['transactionId'])) {
            throw new TransientPaymentException('Invalid response from payment service');
        }
        return $data['transactionId'];
    }

    /** @param list<string> $headers */
    private function extractStatusCode(array $headers): ?int
    {
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                return (int)$m[1];
            }
        }
        return null;
    }
}
