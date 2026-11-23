<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AmenityCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class TenantSignupController extends Controller
{
    public function create(): View
    {
        abort_unless(config('platform.mode') === 'cloud', 404);

        return view('auth.host-register');
    }

    public function store(Request $request, \App\Services\GoogleRecaptchaVerifier $recaptcha): RedirectResponse
    {
        abort_unless(config('platform.mode') === 'cloud', 404);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255', 'unique:tenants,name'],
            'contact_email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'in:fr,en'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);
        $slug = Str::slug($validated['company_name']);
        if ($slug === '') {
            throw ValidationException::withMessages(['company_name' => __('messages.auth.company_name_slug_invalid')]);
        }
        Validator::make(['company_name' => $slug], [
            'company_name' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
        ])->validate();

        if (! $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['email' => __('messages.security.captcha_failed')]);
        }

        $locale = $validated['locale'] ?? session('locale', config('app.locale'));

        $user = DB::transaction(function () use ($validated, $locale, $slug): User {
            $tenant = Tenant::query()->create([
                'name' => $validated['company_name'],
                'slug' => $slug,
                'contact_email' => $validated['contact_email'],
                'status' => 'active',
            ]);

            AmenityCatalog::seedForTenant($tenant->id);

            DB::table('settings')->insert([
                ['key' => 'site_name', 'value' => $validated['company_name'], 'type' => 'string', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'contact_email', 'value' => $validated['contact_email'], 'type' => 'string', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'default_locale', 'value' => $locale, 'type' => 'string', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'secondary_locale', 'value' => $locale === 'fr' ? 'en' : 'fr', 'type' => 'string', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
            ]);

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'admin',
                'is_admin' => true,
                'tenant_id' => $tenant->id,
                'locale' => $locale,
                'email_booking_updates' => true,
                'email_marketing' => false,
                'email_newsletter' => false,
            ]);
        });

        Auth::login($user);
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return redirect()->route('admin.dashboard')->with('success', __('messages.auth.host_welcome'));
    }

}
