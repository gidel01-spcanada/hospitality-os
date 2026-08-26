<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()->orderBy('name')->get()->map(function (Tenant $tenant) {
            $tenant->establishments_count = Establishment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $tenant->properties_count = Property::query()
                ->whereHas('establishment', fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenant->id))
                ->count();
            $tenant->users_count = User::query()->where('tenant_id', $tenant->id)->count();

            return $tenant;
        });

        return view('platform.tenants.index', compact('tenants'));
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);

        return redirect()->route('platform.tenants.index')->with('success', __('messages.platform.tenant_suspended', ['name' => $tenant->name]));
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active']);

        return redirect()->route('platform.tenants.index')->with('success', __('messages.platform.tenant_activated', ['name' => $tenant->name]));
    }
}
