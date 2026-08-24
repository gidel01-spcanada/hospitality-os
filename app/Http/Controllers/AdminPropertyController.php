<?php

namespace App\Http\Controllers;

use App\Models\AdminAvailabilityBlock;
use App\Models\Property;
use App\Models\PropertyFeature;
use App\Models\PropertyImage;
use App\Models\Establishment;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Models\ExternalCalendarFeed;

class AdminPropertyController extends Controller
{
    public function index(Request $request): View
    {
        $establishments = Establishment::query()->orderBy('name')->get();
        $establishmentId = $request->validate([
            'establishment' => ['nullable', 'integer', 'exists:establishments,id'],
        ])['establishment'] ?? null;

        $properties = Property::query()
            ->when($establishmentId, fn ($query) => $query->where('establishment_id', $establishmentId))
            ->with('establishment')
            ->latest()
            ->get();

        return view('admin.properties.index', compact('properties', 'establishments', 'establishmentId'));
    }

    public function edit(Property $property): View
    {
        $property->load(['images', 'amenities', 'features', 'translations', 'rateRules', 'availabilityBlocks', 'calendarFeeds.events', 'reservations']);

        $establishments = Establishment::query()->orderBy('name')->get();

        return view('admin.properties.edit', compact('property', 'establishments'));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'property_type' => ['sometimes', 'in:apartment,house,villa,studio,room,other'],
            'slug' => ['required', 'string', 'max:255', 'unique:properties,slug,' . $property->id],
            'nightly_rate_xof' => ['required', 'numeric', 'min:0'],
            'nightly_rate_eur' => ['required', 'numeric', 'min:0'],
            'minimum_stay' => ['required', 'integer', 'min:1'],
            'max_guests' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published,archived'],
            'establishment_id' => ['sometimes', 'integer', 'exists:establishments,id'],
            'translations' => ['nullable', 'array'],
            'translations.fr.name' => ['nullable', 'string', 'max:255'],
            'translations.en.name' => ['nullable', 'string', 'max:255'],
            'translations.fr.summary' => ['nullable', 'string'],
            'translations.en.summary' => ['nullable', 'string'],
            'translations.fr.description' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $property->fill($validated);
        $property->save();

        foreach (['fr', 'en'] as $locale) {
            $content = $validated['translations'][$locale] ?? [];
            if (array_filter($content, fn ($value) => filled($value))) {
                $property->translations()->updateOrCreate(['locale' => $locale], $content);
            }
        }

        return $this->editorRedirect($property, $request, __('messages.flash.property_updated'));
    }

    public function updateImages(Request $request, Property $property): RedirectResponse
    {
        if ($request->filled('remove_image_id')) {
            $image = $property->images()->findOrFail((int) $request->input('remove_image_id'));
            $wasCover = $image->is_cover;
            if (str_starts_with($image->file_path, 'uploads/') && File::exists(public_path($image->file_path))) {
                File::delete(public_path($image->file_path));
            }
            $image->delete();

            if ($wasCover || ! $property->images()->where('is_cover', true)->exists()) {
                $replacement = $property->images()->orderBy('sort_order')->first();
                $property->images()->update(['is_cover' => false]);
                if ($replacement) {
                    $replacement->forceFill(['is_cover' => true])->save();
                    $property->forceFill(['cover_image' => $replacement->file_path])->save();
                } else {
                    $property->forceFill(['cover_image' => null])->save();
                }
            }

            return $this->editorRedirect($property, $request, __('messages.flash.photo_removed'), 'photos');
        }

        $validated = $request->validate([
            'image_order' => ['required', 'array'],
            'image_order.*' => ['required', 'integer', 'distinct', 'min:1'],
            'image_tags' => ['nullable', 'array'],
            'image_tags.*' => ['nullable', 'string', 'max:100'],
            'cover_image_id' => ['required', 'integer'],
        ]);

        asort($validated['image_order'], SORT_NUMERIC);
        $imageIds = array_map('intval', array_keys($validated['image_order']));
        $images = $property->images()->whereIn('id', $imageIds)->get()->keyBy('id');

        if (count($imageIds) !== $images->count() || ! $images->has((int) $validated['cover_image_id'])) {
            return back()->withErrors(['image_order' => __('messages.errors.property_image_ownership')]);
        }

        foreach ($imageIds as $position => $imageId) {
            $images->get($imageId)->forceFill([
                'sort_order' => $position + 1,
                'is_cover' => $imageId === (int) $validated['cover_image_id'],
                'room_tag' => $validated['image_tags'][$imageId] ?? null,
            ])->save();
        }

        $cover = $images->get((int) $validated['cover_image_id']);
        $property->forceFill(['cover_image' => $cover->file_path])->save();

        return $this->editorRedirect($property, $request, __('messages.flash.photos_updated'), 'photos');
    }

    public function uploadImages(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $directory = public_path('uploads/properties/' . $property->slug);
        File::ensureDirectoryExists($directory);
        $nextOrder = ((int) $property->images()->max('sort_order')) + 1;
        $hasCover = $property->images()->where('is_cover', true)->exists();
        $hadCover = $hasCover;
        $firstUploadedPath = null;

        foreach ($validated['photos'] as $file) {
            $filename = $file->hashName();
            $file->move($directory, $filename);
            $dimensions = @getimagesize($directory . DIRECTORY_SEPARATOR . $filename) ?: [];
            $path = 'uploads/properties/' . $property->slug . '/' . $filename;
            $property->images()->create([
                'file_path' => $path,
                'file_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'sort_order' => $nextOrder++,
                'is_cover' => ! $hasCover,
            ]);
            $firstUploadedPath ??= $path;
            $hasCover = true;
        }

        if (! $hadCover && $firstUploadedPath) {
            $property->images()->where('file_path', $firstUploadedPath)->update(['is_cover' => true]);
            $property->forceFill(['cover_image' => $firstUploadedPath])->save();
        }

        return $this->editorRedirect($property, $request, __('messages.flash.photos_uploaded'), 'photos');
    }

    public function storePriceRule(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'nightly_rate_xof' => ['required', 'numeric', 'min:0'],
            'nightly_rate_eur' => ['required', 'numeric', 'min:0'],
            'minimum_stay' => ['required', 'integer', 'min:1'],
            'rule_type' => ['required', 'string', 'max:50'],
        ]);

        $property->rateRules()->create($validated);

        return $this->editorRedirect($property, $request, __('messages.flash.price_rule_saved'), 'rules');
    }

    public function storeAvailabilityBlock(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        AdminAvailabilityBlock::create([
            'property_id' => $property->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
        ]);

        return $this->editorRedirect($property, $request, __('messages.flash.availability_saved'), 'availability');
    }

    public function deleteAvailabilityBlock(Request $request, Property $property, AdminAvailabilityBlock $block): RedirectResponse
    {
        abort_unless($block->property_id === $property->id, 404);
        $block->delete();

        return $this->editorRedirect($property, $request, __('messages.flash.availability_deleted'), 'availability');
    }

    public function storePropertyFeature(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_xof' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $property->features()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cost_xof' => $validated['cost_xof'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->editorRedirect($property, $request, __('messages.flash.feature_saved'), 'features');
    }

    public function availabilityCheck(Property $property, Request $request, AvailabilityService $availabilityService): RedirectResponse|View
    {
        $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $checkIn = \Carbon\Carbon::parse($request->input('check_in'));
        $checkOut = \Carbon\Carbon::parse($request->input('check_out'));
        $available = $availabilityService->isAvailable($property, $checkIn, $checkOut);

        return view('admin.properties.availability', compact('property', 'available', 'checkIn', 'checkOut'));
    }

    private function editorRedirect(Property $property, Request $request, string $message, string $fallbackTab = 'general'): RedirectResponse
    {
        $tab = in_array($request->input('active_tab'), ['general', 'photos', 'rules', 'features', 'availability', 'calendars'], true)
            ? $request->input('active_tab')
            : $fallbackTab;

        return redirect()->to(route('admin.properties.edit', $property) . '#' . $tab)->with('success', $message);
    }
}
