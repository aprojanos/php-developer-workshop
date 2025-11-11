<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Router;

interface ControllerInterface
{
    public function register(Router $router): void;
}

