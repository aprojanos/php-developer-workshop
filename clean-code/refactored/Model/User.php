<?php
declare(strict_types=1);

namespace Refactored\Model;

// PHP 8.1: readonly properties
// PHP 8.0: Constructor Property Promotion
// Ez egy egyszerű DTO (Data Transfer Object)
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone
    ) {}
}
