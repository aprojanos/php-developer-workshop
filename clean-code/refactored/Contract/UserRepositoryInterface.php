<?php
declare(strict_types=1);

namespace Refactored\Contract;

use Refactored\Model\User;

// A Repository "szerződése". Bármilyen adatbázis lehet mögötte.
interface UserRepositoryInterface
{
    public function findById(int $userId): ?User;
}
