<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Order;
use App\Domain\Repository\OrderRepository;
use App\Domain\ValueObject\OrderId;
use PDO;

final class PdoOrderRepository implements OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->initSchema();
    }

    private function initSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS orders (
                id TEXT PRIMARY KEY,
                amount_cents INTEGER NOT NULL,
                currency TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    public function save(Order $order): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO orders (id, amount_cents, currency, status, created_at) VALUES (:id, :amount, :currency, :status, :createdAt)');
        $stmt->execute([
            ':id' => (string)$order->id(),
            ':amount' => $order->amountCents(),
            ':currency' => $order->currency(),
            ':status' => $order->status(),
            ':createdAt' => $order->createdAt()->format(DATE_ATOM),
        ]);
    }

    public function get(OrderId $id): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT id, amount_cents, currency, status, created_at FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (string)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return Order::reconstitute(
            OrderId::fromString($row['id']),
            (int)$row['amount_cents'],
            (string)$row['currency'],
            (string)$row['status'],
            new \DateTimeImmutable((string)$row['created_at'])
        );
    }
}

