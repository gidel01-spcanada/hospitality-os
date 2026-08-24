<?php

namespace App\Http\Controllers;

use App\Models\EmailOutbox;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
            'password' => ['required', 'string'],
        ], [
            'email.exists' => __('messages.errors.account_not_found'),
        ]);
        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $locale = $user?->locale ?? session('locale', config('app.locale'));
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);

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

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'in:fr,en'],
        ]);

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

    public function dashboard(): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        $reservations = Reservation::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]);
            })
            ->with('property')
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard', compact('user', 'reservations'));
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

    public function accountSettings(): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        $locale = $user->locale ?? session('locale', config('app.locale'));
        app()->setLocale($locale);
        session()->put('locale', $locale);

        return view('account.settings', compact('user'));
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

        $reservation->load(['property', 'guest', 'priceLines']);

        return view('dashboard-reservation', compact('user', 'reservation'));
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
            'email_marketing' => ['nullable', 'boolean'],
            'email_newsletter' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $user->forceFill([
            'locale' => $validated['locale'],
            'email_booking_updates' => $request->boolean('email_booking_updates'),
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
