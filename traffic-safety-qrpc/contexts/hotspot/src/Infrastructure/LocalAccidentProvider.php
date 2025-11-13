<?php

namespace HotspotContext\Infrastructure;

use AccidentContext\Application\AccidentService;
use HotspotContext\Application\Port\AccidentProviderInterface;
use SharedKernel\Model\AccidentBase;

/**
 * Fallback provider to use the in-process AccidentService when gRPC is unavailable.
 */
final class LocalAccidentProvider implements AccidentProviderInterface
{
    public function __construct(
        private readonly AccidentService $service
    ) {
    }

    /**
     * @return AccidentBase[]
     */
    public function all(): array
    {
        return $this->service->all();
    }
}

