<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Port\FxConverter;

final class SimpleFxConverter implements FxConverter
{
    /**
     * Very naive FX using fixed rates to USD as pivot.
     * Rates are for demo only.
     */
    private const RATES_TO_USD = [
        'USD' => 1.0,
        'EUR' => 1.10,  // 1 EUR = 1.10 USD
        'UAH' => 0.027, // 1 UAH = 0.027 USD
    ];

    public function __construct(private readonly string $chargeCurrency = 'USD') {}

    public function convert(int $amountCents, string $fromCurrency, string $toCurrency): int
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);
        if (!isset(self::RATES_TO_USD[$from]) || !isset(self::RATES_TO_USD[$to])) {
            throw new \InvalidArgumentException('Unsupported currency for FX conversion');
        }
        if ($from === $to) {
            return $amountCents;
        }
        // Convert via USD pivot using float math; round to nearest cent
        $usdAmount = ($amountCents / 100.0) * self::RATES_TO_USD[$from];
        $targetAmount = $usdAmount / self::RATES_TO_USD[$to];
        return (int) round($targetAmount * 100.0);
    }
}

