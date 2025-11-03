<?php

declare(strict_types=1);

namespace App\Application\UseCase\GetOrder;

final class GetOrderQuery
{
    public function __construct(public readonly string $orderId)
    {
        if ($orderId === '') {
            throw new \InvalidArgumentException('orderId cannot be empty');
        }
    }
}

