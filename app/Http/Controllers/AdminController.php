<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Establishment;
use App\Models\Reservation;
use App\Models\SiteReview;
use App\Models\User;
use App\Support\BrandSettings;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        abort_unless($user && $user->canManageReservations(), 403, 'Admin access required.');

        // Property, Reservation, and User have no tenant_id of their own, so they're
        // filtered here through their establishment (or directly, for users).
        $tenantId = app(CurrentTenant::class)->id();
        $ownedByTenant = fn ($query) => $query->whereHas('establishment', fn ($q) => $q->where('tenant_id', $tenantId));

        $stats = [
            'establishments' => Establishment::count(),
            'properties' => Property::query()->when($tenantId, $ownedByTenant)->count(),
            'bookings' => Reservation::query()->when($tenantId, fn ($query) => $query->whereHas('property.establishment', fn ($q) => $q->where('tenant_id', $tenantId)))->count(),
            'active_users' => User::query()->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))->count(),
            'pending' => Reservation::query()
                ->when($tenantId, fn ($query) => $query->whereHas('property.establishment', fn ($q) => $q->where('tenant_id', $tenantId)))
                ->whereIn('status', ['pending', 'pending_payment'])
                ->count(),
        ];

        return view('admin.dashboard', compact('user', 'stats'));
    }

    public function settings(): View
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $brand = BrandSettings::all();

        return view('admin.settings', compact('user', 'brand'));
    }

    public function users(Request $request): View
    {
        $tenantId = app(CurrentTenant::class)->id();

        $users = User::query()
            // Staff belong to one tenant; customers (tenant_id null) are shared platform users.
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%' . $request->string('search')->trim() . '%';
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('role', 'like', $search);
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function editUser(User $managedUser): View
    {
        return view('admin.users.edit', [
            'managedUser' => $managedUser,
            'establishments' => Establishment::query()->orderBy('name')->get(),
            'assignedEstablishmentIds' => $managedUser->exists ? $managedUser->establishments()->pluck('establishments.id')->all() : [],
        ]);
    }

    public function createUser(): View
    {
        return view('admin.users.edit', [
            'managedUser' => new User(['role' => 'customer', 'locale' => 'fr']),
            'establishments' => Establishment::query()->orderBy('name')->get(),
            'assignedEstablishmentIds' => [],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:customer,admin,concierge,host'],
            'locale' => ['required', 'in:fr,en'],
            'email_booking_updates' => ['nullable', 'boolean'],
            'email_message_updates' => ['nullable', 'boolean'],
            'email_marketing' => ['nullable', 'boolean'],
            'email_newsletter' => ['nullable', 'boolean'],
            'email_verified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'establishments' => ['nullable', 'array'],
            'establishments.*' => ['integer', 'exists:establishments,id'],
        ]);

        $managedUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_admin' => $validated['role'] === 'admin',
            // Customers are shared platform users; only staff belong to the creating admin's tenant.
            'tenant_id' => $validated['role'] !== 'customer' ? app(CurrentTenant::class)->id() : null,
            'locale' => $validated['locale'],
            'email_booking_updates' => $request->boolean('email_booking_updates'),
            'email_message_updates' => $request->boolean('email_message_updates'),
            'email_marketing' => $request->boolean('email_marketing'),
            'email_newsletter' => $request->boolean('email_newsletter'),
            'email_verified_at' => $request->boolean('email_verified') ? now() : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($validated['role'] === 'host') {
            $managedUser->establishments()->sync($validated['establishments'] ?? []);
        }

        return redirect()->route('admin.users.index')->with('status', __('messages.flash.user_created'));
    }

    public function updateUser(Request $request, User $managedUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $managedUser->id],
            'role' => ['required', 'in:customer,admin,concierge,host'],
            'locale' => ['required', 'in:fr,en'],
            'email_booking_updates' => ['nullable', 'boolean'],
            'email_message_updates' => ['nullable', 'boolean'],
            'email_marketing' => ['nullable', 'boolean'],
            'email_newsletter' => ['nullable', 'boolean'],
            'email_verified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'establishments' => ['nullable', 'array'],
            'establishments.*' => ['integer', 'exists:establishments,id'],
        ]);

        if ($managedUser->is($request->user()) && $validated['role'] !== 'admin') {
            return back()->withErrors(['role' => __('messages.errors.self_admin_access')]);
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $managedUser->is_active;

        if ($managedUser->is($request->user()) && ! $isActive) {
            return back()->withErrors(['is_active' => __('messages.errors.self_deactivation')]);
        }

        $managedUser->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_admin' => $validated['role'] === 'admin',
            // Customers are shared platform users; promoting one to staff assigns it to this tenant.
            'tenant_id' => $validated['role'] !== 'customer' ? ($managedUser->tenant_id ?? app(CurrentTenant::class)->id()) : null,
            'locale' => $validated['locale'],
            'email_booking_updates' => $request->boolean('email_booking_updates'),
            'email_message_updates' => $request->boolean('email_message_updates'),
            'email_marketing' => $request->boolean('email_marketing'),
            'email_newsletter' => $request->boolean('email_newsletter'),
            'email_verified_at' => $request->boolean('email_verified') ? ($managedUser->email_verified_at ?? now()) : null,
            'is_active' => $isActive,
        ])->save();

        $managedUser->establishments()->sync($validated['role'] === 'host' ? ($validated['establishments'] ?? []) : []);

        return redirect()->route('admin.users.index')->with('status', __('messages.flash.user_updated'));
    }

    public function deleteUser(User $managedUser): RedirectResponse
    {
        abort_if($managedUser->is(auth()->user()), 422, __('messages.errors.self_deactivation'));

        $managedUser->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $rules = [
            'site_name' => ['required', 'string', 'max:255'],
            'site_icon' => ['required', 'string', 'max:3'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['required', 'in:fr,en'],
            'secondary_locale' => ['required', 'in:fr,en'],
            'review_source_booking_url' => ['nullable', 'url', 'max:2048'],
            'review_source_google_url' => ['nullable', 'url', 'max:2048'],
            'email_sender_name' => ['nullable', 'string', 'max:255'],
            'email_sender_email' => ['nullable', 'email', 'max:255'],
            'guest_account_setup_subject' => ['nullable', 'string', 'max:255'],
            'guest_account_setup_message' => ['nullable', 'string'],
            'customer_confirmation_subject' => ['nullable', 'string', 'max:255'],
            'customer_confirmation_message' => ['nullable', 'string'],
            'pre_arrival_subject' => ['nullable', 'string', 'max:255'],
            'pre_arrival_message' => ['nullable', 'string'],
            'post_stay_subject' => ['nullable', 'string', 'max:255'],
            'post_stay_message' => ['nullable', 'string'],
        ];

        $rules['customer_theme'] = config('platform.mode') === 'on_premise'
            ? ['required', 'in:emerald-gold,ocean-coral,terracotta-teal,sunrise-ink']
            : ['nullable', 'in:emerald-gold,ocean-coral,terracotta-teal,sunrise-ink'];

        $validated = $request->validate($rules);

        BrandSettings::set($validated);
        config()->set('app.name', $validated['site_name']);

        return redirect()->route('admin.settings')->with('status', __('messages.flash.settings_saved'));
    }

    public function reviews(): View
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $reviews = SiteReview::query()->orderByDesc('reviewed_at')->get();
        $brand = BrandSettings::all();

        return view('admin.reviews', compact('user', 'reviews', 'brand'));
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $validated = $request->validate([
            'source' => ['required', 'in:booking,google,airbnb'],
            'reviewer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'reviewed_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        SiteReview::query()->create([
            'source' => $validated['source'],
            'reviewer_name' => $validated['reviewer_name'],
            'rating' => $validated['rating'],
            'review_text' => $validated['review_text'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'reviewed_at' => $validated['reviewed_at'] ?? now(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.reviews')->with('status', __('messages.flash.review_saved'));
    }

    public function updateReview(Request $request, SiteReview $review): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $validated = $request->validate([
            'source' => ['required', 'in:booking,google,airbnb'],
            'reviewer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'reviewed_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $review->update([
            'source' => $validated['source'],
            'reviewer_name' => $validated['reviewer_name'],
            'rating' => $validated['rating'],
            'review_text' => $validated['review_text'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'reviewed_at' => $validated['reviewed_at'] ?? $review->reviewed_at ?? now(),
            'is_active' => $request->boolean('is_active', $review->is_active),
        ]);

        return redirect()->route('admin.reviews')->with('status', __('messages.flash.review_updated'));
    }

    public function deleteReview(SiteReview $review): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $review->delete();

        return redirect()->route('admin.reviews')->with('status', __('messages.flash.review_deleted'));
    }

    public function importReviews(Request $request): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $csv = trim((string) $request->input('csv'));
        if ($csv === '') {
            return back()->withErrors(['csv' => 'Le contenu CSV est requis.']);
        }

        $inserted = app(\App\Services\ReviewImportService::class)->importGenericCsv($csv, null, null);

        return redirect()->route('admin.reviews')->with('status', __('messages.flash.reviews_imported', ['count' => $inserted]));
    }
}
