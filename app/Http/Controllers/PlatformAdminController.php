<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function create(): View
    {
        return view('platform.tenants.create');
    }

    public function store(Request $request, AccountInvitationService $invitations): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $tenant = Tenant::query()->create([
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']),
            'contact_email' => $validated['contact_email'] ?? null,
            'status' => 'active',
        ]);

        $admin = User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Str::password(32),
            'role' => 'admin',
            'is_admin' => true,
            'tenant_id' => $tenant->id,
            'locale' => 'fr',
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $invitations->send($admin);

        return redirect()->route('platform.tenants.index')->with('success', __('messages.platform.tenant_created', ['name' => $tenant->name]));
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

