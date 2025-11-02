<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\CreateOrder;

use App\Application\Exception\OrderRejectedException;
use App\Application\Exception\TransientPaymentException;
use App\Application\Exception\UnsupportedCurrencyException;
use App\Application\Port\PaymentGateway;
use App\Application\UseCase\CreateOrder\CreateOrderCommand;
use App\Application\UseCase\CreateOrder\CreateOrderHandler;
use App\Infrastructure\Persistence\InMemoryOrderRepository;
use App\Infrastructure\Service\SimpleFxConverter;
use App\Infrastructure\Service\SimplePromoService;
use App\Infrastructure\Service\SimpleRiskChecker;
use PHPUnit\Framework\TestCase;

final class CreateOrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orders;
    private SimpleFxConverter $fx;
    private SimplePromoService $promos;
    private SimpleRiskChecker $risk;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->fx = new SimpleFxConverter();
        $this->promos = new SimplePromoService();
        $this->risk = new SimpleRiskChecker();
    }

    public function test_happy_path_with_discount_and_fx(): void
    {
        $payments = new class implements PaymentGateway {
            public function charge(int $amountCents, string $currency): string
            {
                // amount should be 9900 cents (EUR 100.00 -> -10% -> EUR 90.00 -> USD ~99.00)
                TestCase::assertSame(9900, $amountCents);
                TestCase::assertSame('USD', strtoupper($currency));
                return 'tx_fixed';
            }
        };

        $handler = new CreateOrderHandler($this->orders, $payments, $this->fx, $this->promos, $this->risk);
        $event = $handler(new CreateOrderCommand(10000, 'eur', 'PROMO10'));

        self::assertSame(10000, $event->amountCents);
        self::assertSame('EUR', $event->currency);
        self::assertSame(9900, $event->chargedAmountCents);
        self::assertSame('USD', $event->chargedCurrency);
        self::assertSame(10, $event->appliedDiscountPercent);
        self::assertSame('tx_fixed', $event->transactionId);
    }

    public function test_unsupported_currency(): void
    {
        $this->expectException(UnsupportedCurrencyException::class);
        $payments = new class implements PaymentGateway {
            public function charge(int $amountCents, string $currency): string { return 'tx_fixed'; }
        };
        $handler = new CreateOrderHandler($this->orders, $payments, $this->fx, $this->promos, $this->risk);
        $handler(new CreateOrderCommand(1000, 'GBP'));
    }

    public function test_risk_rejection(): void
    {
        $this->expectException(OrderRejectedException::class);
        $payments = new class implements PaymentGateway {
            public function charge(int $amountCents, string $currency): string { return 'tx_fixed'; }
        };
        $handler = new CreateOrderHandler($this->orders, $payments, $this->fx, $this->promos, $this->risk);
        // USD 1000.01 -> above risk threshold
        $handler(new CreateOrderCommand(100001, 'USD'));
    }

    public function test_retry_on_transient_payment_then_success(): void
    {
        $attempts = 0;
        $payments = new class($attempts) implements PaymentGateway {
            private int $attempts;
            public function __construct(& $attempts) { $this->attempts = & $attempts; }
            public function charge(int $amountCents, string $currency): string
            {
                $this->attempts++;
                if ($this->attempts < 3) {
                    throw new TransientPaymentException('Temporary');
                }
                return 'tx_after_retry';
            }
        };
        $handler = new CreateOrderHandler($this->orders, $payments, $this->fx, $this->promos, $this->risk);
        $event = $handler(new CreateOrderCommand(1000, 'USD'));
        self::assertSame('tx_after_retry', $event->transactionId);
    }

    public function test_free_order_skips_payment(): void
    {
        $called = false;
        $payments = new class($called) implements PaymentGateway {
            private bool $called;
            public function __construct(& $called) { $this->called = & $called; }
            public function charge(int $amountCents, string $currency): string
            {
                $this->called = true;
                throw new \RuntimeException('Should not be called for free orders');
            }
        };
        $handler = new CreateOrderHandler($this->orders, $payments, $this->fx, $this->promos, $this->risk);
        $event = $handler(new CreateOrderCommand(5000, 'USD', 'FREE100'));
        self::assertStringStartsWith('free_', $event->transactionId);
        self::assertFalse($called, 'Payment gateway must not be called for free orders');
        self::assertSame(0, $event->chargedAmountCents);
    }
}

