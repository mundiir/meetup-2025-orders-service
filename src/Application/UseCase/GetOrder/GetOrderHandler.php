<?php

declare(strict_types=1);

namespace App\Application\UseCase\GetOrder;

use App\Application\Exception\OrderNotFoundException;
use App\Domain\Repository\OrderRepository;
use App\Domain\ValueObject\OrderId;

final class GetOrderHandler
{
    public function __construct(private readonly OrderRepository $orders)
    {
    }

    public function __invoke(GetOrderQuery $query): GetOrderResult
    {
        $id = OrderId::fromString($query->orderId);
        $order = $this->orders->get($id);
        if ($order === null) {
            throw new OrderNotFoundException('Order not found');
        }

        return new GetOrderResult(
            (string)$order->id(),
            $order->amountCents(),
            $order->currency(),
            $order->status(),
            $order->createdAt()
        );
    }
}

