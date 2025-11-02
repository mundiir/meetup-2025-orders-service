<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Order;
use App\Domain\Repository\OrderRepository;
use App\Domain\ValueObject\OrderId;

final class InMemoryOrderRepository implements OrderRepository
{
    /** @var array<string, Order> */
    private array $storage = [];

    public function save(Order $order): void
    {
        $this->storage[(string)$order->id()] = $order;
    }

    public function get(OrderId $id): ?Order
    {
        return $this->storage[(string)$id] ?? null;
    }
}

