<?php

namespace App\Http\Controllers;

use App\Models\EmailOutbox;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, \App\Services\GoogleRecaptchaVerifier $recaptcha): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ], [
            'email.exists' => __('messages.errors.account_not_found'),
        ]);
        if (! $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['email' => __('messages.security.captcha_failed')]);
        }
        unset($credentials['g-recaptcha-response']);
        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $locale = $user?->locale ?? session('locale', config('app.locale'));
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);

            if ($request->session()->has('pending_public_reservation')) {
                return redirect()->route('reservation.resume');
            }

            return redirect($user && $user->canManageReservations() ? route('admin.dashboard') : route('dashboard'));
        }

        return back()->withErrors([
            'email' => __('messages.errors.credentials'),
        ])->onlyInput('email');
    }

    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, \App\Services\GoogleRecaptchaVerifier $recaptcha): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'in:fr,en'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);
        if (! $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['email' => __('messages.security.captcha_failed')]);
        }
        unset($validated['g-recaptcha-response']);

        $locale = $validated['locale'] ?? session('locale', config('app.locale'));

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'customer',
            'is_admin' => false,
            'locale' => $locale,
            'email_booking_updates' => true,
            'email_marketing' => false,
            'email_newsletter' => false,
        ]);

            Auth::login($user);
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return redirect()->route('dashboard');
    }

    public function dashboard(Request $request): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        $statuses = ['pending', 'pending_payment', 'pending_validation', 'payment_failed', 'confirmed', 'checked_in', 'completed', 'cancelled'];
        $statusFilter = $request->string('status')->toString();

        if (! in_array($statusFilter, [...$statuses, 'all'], true)) {
            $statusFilter = 'active';
        }

        $reservations = Reservation::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]);
            })
            ->when($statusFilter === 'active', fn ($query) => $query->whereNotIn('status', ['cancelled', 'completed']))
            ->when(in_array($statusFilter, $statuses, true), fn ($query) => $query->where('status', $statusFilter))
            ->with(['property', 'receipts'])
            ->orderByDesc('created_at')
            ->get();
        $favoriteProperties = $user->favoriteProperties()
            ->published()
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations'])
            ->orderByDesc('property_favorites.created_at')
            ->get();

        return view('dashboard', compact('user', 'reservations', 'favoriteProperties', 'statuses', 'statusFilter'));
    }

    public function accountProfile(): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return view('account.profile', compact('user'));
    }

    public function accountPreferences(): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return view('account.preferences', compact('user'));
    }

    public function accountSecurity(): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return view('account.security', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
        ])->save();

        return redirect()->route('account.profile')->with('status', __('messages.profile.updated'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect()->route('account.security')->with('status', __('messages.security.updated'));
    }

    public function reservationDetail(Reservation $reservation): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless(
            $reservation->user_id === $user->id || strtolower((string) $reservation->email) === strtolower($user->email),
            403,
            __('messages.errors.reservation_access')
        );

        $reservation->load(['property.establishment', 'property.features', 'guest', 'priceLines', 'receipts']);
        $selectedFeatureIds = $reservation->property->features
            ->filter(fn ($feature) => $reservation->priceLines->contains('label', 'Option: ' . $feature->name))
            ->pluck('id')
            ->all();

        return view('dashboard-reservation', compact('user', 'reservation', 'selectedFeatureIds'));
    }

    public function updateReservation(Request $request, Reservation $reservation, AvailabilityService $availabilityService): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless(
            $reservation->user_id === $user->id || strtolower((string) $reservation->email) === strtolower((string) $user->email),
            403,
            __('messages.errors.reservation_access')
        );
        abort_unless($reservation->status !== 'cancelled' && $reservation->status !== 'completed', 422, __('messages.reservation.modification_unavailable'));

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'selected_features' => ['nullable', 'array'],
            'selected_features.*' => ['integer', Rule::exists('property_features', 'id')->where(fn ($query) => $query->where('property_id', $reservation->property_id)->where('is_active', true))],
        ]);

        $property = $reservation->property()->with('establishment')->firstOrFail();
        $checkIn = now()->parse($validated['check_in']);
        $checkOut = now()->parse($validated['check_out']);
        $adults = (int) $validated['adults'];
        $children = (int) ($validated['children'] ?? 0);
        $infants = (int) ($validated['infants'] ?? 0);
        $nights = $checkIn->diffInDays($checkOut);

        abort_unless($nights >= (int) ($property->minimum_stay ?? 1), 422, __('messages.reservation.modification_minimum_stay'));
        abort_unless($property->max_guests >= $adults + $children, 422, __('messages.reservation.modification_capacity'));
        abort_unless($availabilityService->isAvailable($property, $checkIn, $checkOut, $reservation->id), 422, __('messages.reservation.modification_unavailable_dates'));

        $selectedFeatures = $property->features()->whereIn('id', $validated['selected_features'] ?? [])->where('is_active', true)->get();
        $pricing = PricingCalculator::calculate($property, $checkIn, $checkOut, $adults, $children, $selectedFeatures);
        $amountChanged = round((float) $reservation->total_amount, 2) !== round((float) $pricing['total_amount'], 2);

        $reservation->update([
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'subtotal' => $pricing['subtotal'],
            'fees' => $pricing['fees'],
            'taxes' => $pricing['taxes'],
            'total_amount' => $pricing['total_amount'],
            'status' => $amountChanged ? 'pending_payment' : $reservation->status,
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Reservation modified by customer.' . ($amountChanged ? ' Payment authorization must be updated.' : '')),
        ]);

        $reservation->priceLines()->delete();
        $lines = array_map(function (array $line) use ($reservation): array {
            return array_merge($line, ['reservation_id' => $reservation->id, 'created_at' => now(), 'updated_at' => now()]);
        }, $pricing['price_lines']);
        \App\Models\ReservationPriceLine::query()->insert($lines);

        return redirect()->route('dashboard.reservations.show', $reservation)
            ->with('status', $amountChanged ? __('messages.reservation.modification_payment_required') : __('messages.reservation.modified'));
    }

    public function switchLanguage(Request $request, string $locale): RedirectResponse
    {
        if ($request->user()) {
            $locale = $request->user()->locale ?? config('app.locale');
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);

            return redirect()->back();
        }

        $locale = in_array($locale, ['fr', 'en'], true) ? $locale : config('app.locale');

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return redirect()->back()->withCookie(cookie('locale', $locale, 525600));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:fr,en'],
            'email_booking_updates' => ['nullable', 'boolean'],
            'email_message_updates' => ['nullable', 'boolean'],
            'email_marketing' => ['nullable', 'boolean'],
            'email_newsletter' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $user->forceFill([
            'locale' => $validated['locale'],
            'email_booking_updates' => $request->boolean('email_booking_updates'),
            'email_message_updates' => $request->boolean('email_message_updates'),
            'email_marketing' => $request->boolean('email_marketing'),
            'email_newsletter' => $request->boolean('email_newsletter'),
        ])->save();

        app()->setLocale($validated['locale']);
        $request->session()->put('locale', $validated['locale']);

        return redirect()->route('dashboard')->with('status', __('messages.profile.saved'));
    }

    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            EmailOutbox::query()->create([
                'template' => 'password_reset_requested',
                'recipient_email' => strtolower($request->input('email')),
                'status' => 'queued',
                'payload' => ['subject' => 'Réinitialisation du mot de passe'],
            ]);

            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ])->setRememberToken(str()->random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
