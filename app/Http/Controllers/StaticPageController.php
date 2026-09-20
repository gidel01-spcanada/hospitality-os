<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SiteReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function contact(): View
    {
        return view('contact');
    }

    public function about(): View
    {
        return view('about');
    }

    public function faq(): View
    {
        return view('faq');
    }

    public function reviews(Request $request): View
    {
        $reviewQuery = SiteReview::query()->active();
        $reviewStats = [
            'count' => (clone $reviewQuery)->count(),
            'average' => round((float) (clone $reviewQuery)->avg('rating'), 1),
        ];
        $reviews = (clone $reviewQuery)->orderByDesc('reviewed_at')->paginate(18)->withQueryString();
        $returnProperty = $request->filled('property')
            ? Property::query()->published()->where('slug', $request->string('property')->toString())->first()
            : null;
        $backUrl = $returnProperty ? route('properties.show', $returnProperty) : route('home');
        $backLabel = $returnProperty ? __('messages.reviews.back_property') : __('messages.reviews.back_home');

        return view('reviews.index', compact('reviews', 'reviewStats', 'backUrl', 'backLabel'));
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }

    public function cookies(): View
    {
        return view('legal.cookies');
    }
}
