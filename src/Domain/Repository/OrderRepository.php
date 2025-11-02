<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Order;
use App\Domain\ValueObject\OrderId;

interface OrderRepository
{
    public function save(Order $order): void;

    public function get(OrderId $id): ?Order;
}

