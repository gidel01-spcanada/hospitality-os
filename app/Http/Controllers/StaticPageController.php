<?php

namespace App\Http\Controllers;

use App\Models\SiteReview;
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

    public function reviews(): View
    {
        $reviewQuery = SiteReview::query()->active();
        $reviewStats = [
            'count' => (clone $reviewQuery)->count(),
            'average' => round((float) (clone $reviewQuery)->avg('rating'), 1),
        ];
        $reviews = (clone $reviewQuery)->orderByDesc('reviewed_at')->paginate(18);

        return view('reviews.index', compact('reviews', 'reviewStats'));
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
