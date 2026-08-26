<?php

namespace App\Models\Concerns;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters queries to the current request's tenant, and stamps new rows with it.
 * A no-op outside an authenticated admin/staff session, so public queries are
 * never filtered -- they simply have no tenant context to filter by.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            if ($tenantId = app(CurrentTenant::class)->id()) {
                $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model): void {
            $model->tenant_id ??= app(CurrentTenant::class)->id();
        });
    }
}
