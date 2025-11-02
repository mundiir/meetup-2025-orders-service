<?php

declare(strict_types=1);

namespace App\Interfaces\Http;

use App\Application\Exception\OrderRejectedException;
use App\Application\Exception\TransientPaymentException;
use App\Application\Exception\UnsupportedCurrencyException;
use App\Application\UseCase\CreateOrder\CreateOrderCommand;
use App\Application\UseCase\CreateOrder\CreateOrderHandler;

final class OrderController
{
    public function __construct(private readonly CreateOrderHandler $handler)
    {
    }

    public function create(): array
    {
        $body = (string)file_get_contents('php://input');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [400, ['error' => 'Invalid JSON body']];
        }
        $amount = $data['amountCents'] ?? null;
        $currency = $data['currency'] ?? null;
        $promoCode = $data['promoCode'] ?? null;

        if (!is_int($amount)) {
            return [422, ['error' => 'amountCents must be an integer']];
        }
        if (!is_string($currency) || $currency === '') {
            return [422, ['error' => 'currency must be a non-empty string']];
        }
        if (!is_null($promoCode) && !is_string($promoCode)) {
            return [422, ['error' => 'promoCode must be a string if provided']];
        }

        try {
            $event = ($this->handler)(new CreateOrderCommand($amount, $currency, $promoCode));
        } catch (UnsupportedCurrencyException $e) {
            return [400, ['error' => $e->getMessage()]];
        } catch (OrderRejectedException $e) {
            return [403, ['error' => $e->getMessage()]];
        } catch (TransientPaymentException $e) {
            return [503, ['error' => 'Payment temporarily unavailable']];
        } catch (\Throwable $e) {
            return [400, ['error' => $e->getMessage()]];
        }

        return [201, [
            'orderId' => (string)$event->orderId,
            'amountCents' => $event->amountCents,
            'currency' => $event->currency,
            'occurredAt' => $event->occurredAt->format(DATE_ATOM),
            'chargedAmountCents' => $event->chargedAmountCents,
            'chargedCurrency' => $event->chargedCurrency,
            'appliedDiscountPercent' => $event->appliedDiscountPercent,
            'transactionId' => $event->transactionId,
        ]];
    }
}
