<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final class OrderId
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        // naive UUID format check (8-4-4-4-12) or any non-empty string
        if ($value === '') {
            throw new \InvalidArgumentException('OrderId cannot be empty');
        }
        return new self($value);
    }

    public static function generate(): self
    {
        $data = random_bytes(16);
        // Set version to 4
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set variant to RFC 4122
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        $uuid = sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
        return new self($uuid);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}

