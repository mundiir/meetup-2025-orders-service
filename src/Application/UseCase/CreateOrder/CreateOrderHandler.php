<?php

declare(strict_types=1);

namespace App\Application\UseCase\CreateOrder;

use App\Application\Exception\OrderRejectedException;
use App\Application\Exception\TransientPaymentException;
use App\Application\Exception\UnsupportedCurrencyException;
use App\Application\Port\FxConverter;
use App\Application\Port\PaymentGateway;
use App\Application\Port\PromoService;
use App\Application\Port\RiskChecker;
use App\Domain\Entity\Order;
use App\Domain\Event\OrderCreated;
use App\Domain\Repository\OrderRepository;

final class CreateOrderHandler
{
    private const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'UAH'];
    private const CHARGE_CURRENCY = 'USD';

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentGateway $payments,
        private readonly FxConverter $fx,
        private readonly PromoService $promos,
        private readonly RiskChecker $risk
    ) {}

    public function __invoke(CreateOrderCommand $cmd): OrderCreated
    {
        $currency = strtoupper($cmd->currency);
        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new UnsupportedCurrencyException("Unsupported currency: {$currency}");
        }

        $order = Order::create($cmd->amountCents, $currency);

        // 1) Promo discount (0..100%)
        $discountPercent = max(0, min(100, $this->promos->getDiscountPercent($cmd->promoCode, $currency)));
        $discountedAmount = (int) round($cmd->amountCents * (100 - $discountPercent) / 100.0);

        // 2) FX conversion to charge currency
        $chargedCurrency = self::CHARGE_CURRENCY;
        $chargedAmount = $this->fx->convert($discountedAmount, $currency, $chargedCurrency);

        // 3) Risk check
        if (!$this->risk->isAllowed($order, $chargedAmount, $chargedCurrency)) {
            throw new OrderRejectedException('Order rejected by risk engine');
        }

        // 4) Payment (with retries on transient errors). If free (0), skip charge.
        $transactionId = 'free_' . bin2hex(random_bytes(4));
        if ($chargedAmount > 0) {
            $maxAttempts = 3;
            $attempt = 0;
            while (true) {
                $attempt++;
                try {
                    $transactionId = $this->payments->charge($chargedAmount, $chargedCurrency);
                    break;
                } catch (TransientPaymentException $e) {
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }
                    usleep((int) (50000 * (2 ** ($attempt - 1)))); // 50ms, 100ms
                }
            }
        }

        $this->orders->save($order);

        return new OrderCreated(
            $order->id(),
            $order->amountCents(),
            $order->currency(),
            new \DateTimeImmutable(),
            $chargedAmount,
            $chargedCurrency,
            $discountPercent,
            $transactionId
        );
    }
}
