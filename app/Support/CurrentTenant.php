<?php

namespace App\Support;

/**
 * Per-request tenant context. Empty outside an authenticated admin/staff session,
 * so public/guest queries never pick up a tenant filter.
 */
class CurrentTenant
{
    private ?int $tenantId = null;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
