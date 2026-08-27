<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPreferencesController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.preferences', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ])->save();

        return back()->with('success', __('messages.profile.updated'));
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:fr,en'],
            'timezone' => ['required', 'timezone'],
        ]);

        $request->user()->forceFill($validated)->save();
        app()->setLocale($validated['locale']);
        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('messages.admin.locale_preferences_updated'));
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light-admin,dark-admin,emerald,gold'],
        ]);

        $request->user()->forceFill($validated)->save();

        return back()->with('success', __('messages.admin.theme_updated'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('success', __('messages.security.updated'));
    }
}
