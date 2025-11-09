<?php

namespace App\Contract;

use App\Model\Intersection;

interface IntersectionRepositoryInterface
{
    public function save(Intersection $intersection): void;
    /** @return Intersection[] */
    public function all(): array;
    public function findById(int $id): ?Intersection;
    public function update(Intersection $intersection): void;
    public function delete(int $id): void;
}

