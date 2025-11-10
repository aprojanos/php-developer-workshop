<?php

namespace App\ValueObject;

final readonly class MonetaryAmount
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD'
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Monetary amount cannot be negative');
        }
    }

    public function equals(MonetaryAmount $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function add(MonetaryAmount $other): MonetaryAmount
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add amounts with different currencies');
        }
        return new MonetaryAmount($this->amount + $other->amount, $this->currency);
    }

    public function subtract(MonetaryAmount $other): MonetaryAmount
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot subtract amounts with different currencies');
        }
        return new MonetaryAmount($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): MonetaryAmount
    {
        return new MonetaryAmount($this->amount * $multiplier, $this->currency);
    }

    public function __toString(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->amount);
    }

    public function toFloat(): float
    {
        return $this->amount;
    }
}

