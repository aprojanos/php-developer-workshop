<?php

namespace HotspotContext\Application\Port;

use SharedKernel\Model\AccidentBase;

/**
 * Abstraction over accident data source (gRPC or local).
 *
 * @internal for application use only.
 */
interface AccidentProviderInterface
{
    /**
     * @return AccidentBase[]
     */
    public function all(): array;
}

