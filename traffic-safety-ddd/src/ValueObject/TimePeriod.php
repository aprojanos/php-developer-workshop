<?php

namespace App\ValueObject;

final readonly class TimePeriod
{
    public function __construct(
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate
    ) {
        if ($this->endDate < $this->startDate) {
            throw new \InvalidArgumentException('End date must be after start date');
        }
    }

    public function getDurationInDays(): int
    {
        return $this->startDate->diff($this->endDate)->days;
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    public function equals(TimePeriod $other): bool
    {
        return $this->startDate->format('c') === $other->startDate->format('c')
            && $this->endDate->format('c') === $other->endDate->format('c');
    }
}

