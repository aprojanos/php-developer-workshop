<?php

namespace SharedKernel\ValueObject;

final readonly class GeoLocation
{
    public function __construct(
        public string $wkt,
        public ?string $city = null,
        public ?string $street = null
    ) {}

    public function equals(GeoLocation $other): bool
    {
        return $this->wkt === $other->wkt
            && $this->city === $other->city
            && $this->street === $other->street;
    }

    public function __toString(): string
    {
        $parts = [];
        if ($this->street !== null) {
            $parts[] = $this->street;
        }
        if ($this->city !== null) {
            $parts[] = $this->city;
        }
        if (empty($parts)) {
            return $this->wkt;
        }
        return implode(', ', $parts) . ' (' . $this->wkt . ')';
    }
}

