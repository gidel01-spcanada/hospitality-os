<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\AmenityCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAmenityController extends Controller
{
    public function index(Request $request): View
    {
        $categories = AmenityCategory::query()
            ->withCount('amenities')
            ->orderBy('sort_order')
            ->get();

        $selectedCategory = $categories->firstWhere('id', (int) $request->query('category'))
            ?? $categories->first();

        $selectedCategory?->load(['amenities' => fn ($query) => $query->ordered()]);

        return view('admin.amenities.index', compact('categories', 'selectedCategory'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
        ]);

        $slug = Str::slug($validated['name_en']);

        $category = AmenityCategory::query()->create([
            'slug' => $slug,
            'name_en' => $validated['name_en'],
            'name_fr' => $validated['name_fr'],
            'sort_order' => ((int) AmenityCategory::query()->max('sort_order')) + 10,
        ]);

        return $this->toCategory($category)->with('success', __('messages.flash.amenity_category_created'));
    }

    public function updateCategory(Request $request, AmenityCategory $amenityCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
        ]);

        $amenityCategory->update($validated);

        return $this->toCategory($amenityCategory)->with('success', __('messages.flash.amenity_category_updated'));
    }

    public function destroyCategory(AmenityCategory $amenityCategory): RedirectResponse
    {
        if ($amenityCategory->amenities()->exists()) {
            return $this->toCategory($amenityCategory)->withErrors(['category' => __('messages.errors.amenity_category_in_use')]);
        }

        $amenityCategory->delete();

        return redirect()->route('admin.amenities.index')->with('success', __('messages.flash.amenity_category_deleted'));
    }

    public function storeAmenity(Request $request, AmenityCategory $amenityCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
        ]);

        $amenityCategory->amenities()->create([
            'slug' => $this->uniqueAmenitySlug($validated['name_en']),
            'name_en' => $validated['name_en'],
            'name_fr' => $validated['name_fr'],
            'sort_order' => ((int) $amenityCategory->amenities()->max('sort_order')) + 10,
        ]);

        return $this->toCategory($amenityCategory)->with('success', __('messages.flash.amenity_created'));
    }

    public function updateAmenity(Request $request, Amenity $amenity): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', 'exists:amenity_categories,id'],
        ]);

        $amenity->update($validated);

        return $this->toCategory($validated['category_id'])->with('success', __('messages.flash.amenity_updated'));
    }

    public function destroyAmenity(Amenity $amenity): RedirectResponse
    {
        $categoryId = $amenity->category_id;
        $amenity->delete();

        return $this->toCategory($categoryId)->with('success', __('messages.flash.amenity_deleted'));
    }

    private function toCategory(AmenityCategory|int $category): RedirectResponse
    {
        $categoryId = $category instanceof AmenityCategory ? $category->id : $category;

        return redirect()->route('admin.amenities.index', ['category' => $categoryId]);
    }

    private function uniqueAmenitySlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Amenity::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
