<?php

namespace Aesis\Maintenance;

use Aesis\Maintenance\Services\MaintenancePolicy;

final class Maintenance
{
    public function __construct(private readonly MaintenancePolicy $policy) {}

    /** @return array<string, mixed>|null */
    public function state(): ?array
    {
        return $this->policy->state();
    }

    public function phase(): string
    {
        return $this->policy->phase();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->policy->payload();
    }
}
