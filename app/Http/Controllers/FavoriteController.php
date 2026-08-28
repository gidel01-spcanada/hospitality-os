<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Property $property): RedirectResponse
    {
        abort_unless($property->status === 'published', 404);

        $user = $request->user();
        $favorite = $user->favoriteProperties()->whereKey($property->id)->exists();

        if ($favorite) {
            $user->favoriteProperties()->detach($property->id);
        } else {
            $user->favoriteProperties()->attach($property->id);
        }

        return back()->with('status', $favorite ? __('messages.favorites.removed') : __('messages.favorites.added'));
    }
}