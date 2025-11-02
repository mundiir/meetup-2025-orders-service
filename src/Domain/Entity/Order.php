<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\OrderId;

final class Order
{
    public const STATUS_CREATED = 'created';

    private OrderId $id;
    private int $amountCents;
    private string $currency;
    private string $status;
    private \DateTimeImmutable $createdAt;

    private function __construct(OrderId $id, int $amountCents, string $currency, string $status, \DateTimeImmutable $createdAt)
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount must be positive in cents');
        }
        if ($currency === '') {
            throw new \InvalidArgumentException('Currency cannot be empty');
        }
        $this->id = $id;
        $this->amountCents = $amountCents;
        $this->currency = strtoupper($currency);
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    public static function create(int $amountCents, string $currency): self
    {
        return new self(OrderId::generate(), $amountCents, $currency, self::STATUS_CREATED, new \DateTimeImmutable());
    }

    public static function reconstitute(OrderId $id, int $amountCents, string $currency, string $status, \DateTimeImmutable $createdAt): self
    {
        return new self($id, $amountCents, $currency, $status, $createdAt);
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

