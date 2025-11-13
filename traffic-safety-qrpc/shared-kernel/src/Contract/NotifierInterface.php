<?php
namespace SharedKernel\Contract;

interface NotifierInterface
{
    /**
     * Notify about a new accident.
     *
     * @param array $payload free-form payload (or pass domain object)
     * @return void
     */
    public function notify(array $payload): void;
}
