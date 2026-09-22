<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\AdminEstablishmentController;
use App\Http\Controllers\AdminPreferencesController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\CleaningScheduleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicPropertyController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AdminLogController;
use App\Models\Property;
use App\Models\Establishment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware('locale')->get('/', function (\Illuminate\Http\Request $request) {
    $properties = collect();
    $establishments = collect();
    $reviews = collect();
    $reviewStats = ['count' => 0, 'average' => null];
    $destinations = collect();
    $cardPricing = [];
    $favoritePropertyIds = auth()->user()?->favoriteProperties()->pluck('properties.id')->all() ?? [];
    $homeFilters = $request->validate([
        'establishment' => ['nullable', 'integer'],
        'destination' => ['nullable', 'string', 'max:120'],
        'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
        'check_in' => ['nullable', 'date', 'after_or_equal:today'],
        'check_out' => ['nullable', 'date', 'after:check_in'],
        'sort' => ['nullable', 'in:recommended,price_asc,price_desc'],
    ]);

    if (collect(['establishment', 'destination', 'guests', 'check_in', 'check_out', 'sort'])
        ->contains(fn (string $key): bool => $request->query->has($key))) {
        return redirect()->route('properties.index', array_filter($homeFilters, static fn ($value) => $value !== null && $value !== ''));
    }

    if (Schema::hasTable('properties')) {
        $establishments = Establishment::query()->where('is_active', true)->with('translations')->withCount(['properties' => fn ($query) => $query->published()])->orderBy('name')->get();
        $destinations = Property::query()->published()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $properties = Property::query()
            ->published()
            ->when($request->integer('establishment'), fn ($query, $id) => $query->where('establishment_id', $id))
            ->when($homeFilters['destination'] ?? null, fn ($query, $value) => $query->where('city', 'like', '%' . $value . '%'))
            ->when(($homeFilters['guests'] ?? null), fn ($query, $guests) => $query->where('max_guests', '>=', $guests))
            ->when(($homeFilters['check_in'] ?? null) && ($homeFilters['check_out'] ?? null), function ($query) use ($homeFilters): void {
                $query->whereDoesntHave('reservations', fn ($reservationQuery) => $reservationQuery
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('check_in', '<', $homeFilters['check_out'])
                    ->whereDate('check_out', '>', $homeFilters['check_in']))
                    ->whereDoesntHave('availabilityBlocks', fn ($blockQuery) => $blockQuery
                        ->whereDate('start_date', '<', $homeFilters['check_out'])
                        ->whereDate('end_date', '>', $homeFilters['check_in']))
                    ->whereDoesntHave('calendarFeeds', fn ($feedQuery) => $feedQuery
                        ->where('is_enabled', true)
                        ->whereHas('events', fn ($eventQuery) => $eventQuery
                            ->whereDate('start_date', '<', $homeFilters['check_out'])
                            ->whereDate('end_date', '>', $homeFilters['check_in'])));
            })
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'reviews' => fn ($query) => $query->active(), 'amenities', 'translations', 'establishment.reviews' => fn ($query) => $query->active()])
            ->get();

        if (($homeFilters['sort'] ?? 'recommended') === 'price_desc') {
            $properties = $properties->sortByDesc('nightly_rate_xof')->values();
        } elseif (($homeFilters['sort'] ?? 'recommended') === 'price_asc') {
            $properties = $properties->sortBy('nightly_rate_xof')->values();
        } else {
            $properties = app(\App\Services\PropertyRecommendationService::class)->sort($properties, $favoritePropertyIds);
        }

        $properties = $properties->take(3)->values();
        if (($homeFilters['check_in'] ?? null) && ($homeFilters['check_out'] ?? null)) {
            foreach ($properties as $property) {
                $cardPricing[$property->id] = \App\Services\PricingCalculator::calculate(
                    $property,
                    \Carbon\Carbon::parse($homeFilters['check_in']),
                    \Carbon\Carbon::parse($homeFilters['check_out']),
                    (int) ($homeFilters['guests'] ?? 1),
                );
            }
        }
    }

    if (Schema::hasTable('site_reviews')) {
        $reviewQuery = \App\Models\SiteReview::query()->where('is_active', true);
        $reviewStats = [
            'count' => (clone $reviewQuery)->count(),
            'average' => round((float) (clone $reviewQuery)->avg('rating'), 1),
        ];
        $reviews = (clone $reviewQuery)->orderByDesc('reviewed_at')->limit(3)->get();
    }

    return view('home', compact('properties', 'establishments', 'reviews', 'reviewStats', 'destinations', 'favoritePropertyIds', 'cardPricing', 'homeFilters'));
})->name('home');

Route::get('/language/{locale}', [AuthController::class, 'switchLanguage'])->name('language.switch');
Route::middleware('signed')->group(function () {
    Route::get('/review/{reservation}', [ReviewController::class, 'create'])->name('reviews.submit');
    Route::post('/review/{reservation}', [ReviewController::class, 'store']);
    Route::get('/review/{reservation}/google', [ReviewController::class, 'google'])->name('reviews.submit.google');
});
Route::middleware(['auth', 'active', 'locale'])->get('/reservation/resume', [PublicPropertyController::class, 'resume'])->name('reservation.resume');

Route::middleware(['guest', 'locale'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/host/register', [\App\Http\Controllers\TenantSignupController::class, 'create'])->name('host.register');
    Route::post('/host/register', [\App\Http\Controllers\TenantSignupController::class, 'store'])->name('host.register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', 'active', 'locale'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/account', [AuthController::class, 'accountProfile'])->name('account.profile');
    Route::post('/account', [AuthController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/preferences', [AuthController::class, 'accountPreferences'])->name('account.preferences');
    Route::get('/account/security', [AuthController::class, 'accountSecurity'])->name('account.security');
    Route::post('/account/security', [AuthController::class, 'updatePassword'])->name('account.security.update');
    Route::post('/dashboard/preferences', [AuthController::class, 'updatePreferences'])->name('dashboard.preferences.update');
    Route::get('/dashboard/reservations/{reservation}', [AuthController::class, 'reservationDetail'])->name('dashboard.reservations.show');
    Route::put('/dashboard/reservations/{reservation}', [AuthController::class, 'updateReservation'])->name('dashboard.reservations.update');
    Route::get('/reservations/{reservation}/receipt', [\App\Http\Controllers\ReceiptController::class, 'download'])->name('reservations.receipt');
    Route::post('/properties/{property}/favorite', [\App\Http\Controllers\FavoriteController::class, 'toggle'])->name('properties.favorite.toggle');
    Route::get('/messages', [\App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [\App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{thread}', [\App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{thread}', [\App\Http\Controllers\MessageController::class, 'reply'])->name('messages.reply');

    Route::middleware('reservation-staff')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/reservations', [AdminReservationController::class, 'index'])->name('admin.reservations.index');
        Route::get('/admin/reservations/create', [AdminReservationController::class, 'create'])->name('admin.reservations.create');
        Route::post('/admin/reservations', [AdminReservationController::class, 'store'])->name('admin.reservations.store');
        Route::get('/admin/reservations/{reservation}/edit', [AdminReservationController::class, 'edit'])->name('admin.reservations.edit');
        Route::put('/admin/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('admin.reservations.update');
        Route::get('/admin/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('admin.reservations.show');
        Route::patch('/admin/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('admin.reservations.update-status');
        Route::delete('/admin/reservations/{reservation}', [AdminReservationController::class, 'destroy'])->middleware('admin')->name('admin.reservations.destroy');
        Route::post('/admin/reservations/{reservation}/payment-link', [AdminReservationController::class, 'sendPaymentLink'])->name('admin.reservations.payment-link.send');
        Route::post('/admin/reservations/{reservation}/payment-proof', [AdminReservationController::class, 'uploadPaymentProof'])->name('admin.reservations.payment-proof.upload');
        Route::post('/admin/reservations/{reservation}/confirm-offline-payment', [\App\Http\Controllers\ReceiptController::class, 'confirmOfflinePayment'])->name('admin.reservations.confirm-offline-payment');
        Route::get('/admin/messages', [\App\Http\Controllers\AdminMessageController::class, 'index'])->name('admin.messages.index');
        Route::get('/admin/messages/{thread}', [\App\Http\Controllers\AdminMessageController::class, 'show'])->name('admin.messages.show');
        Route::post('/admin/messages/{thread}', [\App\Http\Controllers\AdminMessageController::class, 'reply'])->name('admin.messages.reply');
        Route::get('/admin/cleaning', [CleaningScheduleController::class, 'index'])->name('admin.cleaning.index');
        Route::get('/admin/cleaning/create', [CleaningScheduleController::class, 'create'])->name('admin.cleaning.create');
        Route::post('/admin/cleaning', [CleaningScheduleController::class, 'store'])->name('admin.cleaning.store');
        Route::get('/admin/cleaning/generate', [CleaningScheduleController::class, 'generateForm'])->name('admin.cleaning.generate.create');
        Route::post('/admin/cleaning/generate', [CleaningScheduleController::class, 'generateFromDepartures'])->name('admin.cleaning.generate');
        Route::get('/admin/cleaning/{visit}/edit', [CleaningScheduleController::class, 'edit'])->name('admin.cleaning.edit');
        Route::put('/admin/cleaning/{visit}', [CleaningScheduleController::class, 'update'])->name('admin.cleaning.update');
        Route::delete('/admin/cleaning/{visit}', [CleaningScheduleController::class, 'destroy'])->name('admin.cleaning.destroy');
        Route::post('/admin/cleaning/share', [CleaningScheduleController::class, 'share'])->name('admin.cleaning.share');
        Route::delete('/admin/cleaning/shares/{share}', [CleaningScheduleController::class, 'revoke'])->name('admin.cleaning.shares.revoke');
        Route::get('/admin/bookings', [AdminReservationController::class, 'index'])->name('admin.bookings.index');
        Route::get('/admin/bookings/create', [AdminReservationController::class, 'create'])->name('admin.bookings.create');
        Route::post('/admin/bookings', [AdminReservationController::class, 'store'])->name('admin.bookings.store');
        Route::get('/admin/bookings/{reservation}/edit', [AdminReservationController::class, 'edit'])->name('admin.bookings.edit');
        Route::put('/admin/bookings/{reservation}', [AdminReservationController::class, 'update'])->name('admin.bookings.update');
        Route::get('/admin/bookings/{reservation}', [AdminReservationController::class, 'show'])->name('admin.bookings.show');
    });

    Route::middleware('establishment-manager')->group(function () {
        Route::get('/admin/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/admin/reports/export.csv', [AdminReportController::class, 'exportCsv'])->name('admin.reports.export');
        Route::get('/admin/profile', [AdminPreferencesController::class, 'index'])->name('admin.profile');
        Route::get('/admin/preferences', [AdminPreferencesController::class, 'index'])->name('admin.preferences');
        Route::put('/admin/preferences/profile', [AdminPreferencesController::class, 'updateProfile'])->name('admin.preferences.profile.update');
        Route::put('/admin/preferences/locale', [AdminPreferencesController::class, 'updateLocale'])->name('admin.preferences.locale.update');
        Route::put('/admin/preferences/theme', [AdminPreferencesController::class, 'updateTheme'])->name('admin.preferences.theme.update');
        Route::put('/admin/preferences/password', [AdminPreferencesController::class, 'updatePassword'])->name('admin.preferences.password.update');
        Route::get('/admin/establishments', [AdminEstablishmentController::class, 'index'])->name('admin.establishments.index');
        Route::get('/admin/establishments/create', [AdminEstablishmentController::class, 'create'])->name('admin.establishments.create');
        Route::get('/admin/establishments/{establishment}/edit', [AdminEstablishmentController::class, 'edit'])->name('admin.establishments.edit');
        Route::post('/admin/establishments', [AdminEstablishmentController::class, 'store'])->name('admin.establishments.store');
        Route::put('/admin/establishments/{establishment}', [AdminEstablishmentController::class, 'update'])->name('admin.establishments.update');
        Route::get('/admin/establishments/{establishment}/reviews/create', [AdminEstablishmentController::class, 'createReview'])->name('admin.establishments.reviews.create');
        Route::post('/admin/establishments/{establishment}/reviews', [AdminEstablishmentController::class, 'storeReview'])->name('admin.establishments.reviews.store');
        Route::put('/admin/establishments/{establishment}/reviews/{review}', [AdminEstablishmentController::class, 'updateReview'])->name('admin.establishments.reviews.update');
        Route::delete('/admin/establishments/{establishment}/reviews/{review}', [AdminEstablishmentController::class, 'destroyReview'])->name('admin.establishments.reviews.destroy');
        Route::post('/admin/establishments/{establishment}/reviews/import', [AdminEstablishmentController::class, 'importReviews'])->name('admin.establishments.reviews.import');
        Route::post('/admin/establishments/{establishment}/reviews/sync-google', [AdminEstablishmentController::class, 'syncGoogleReviews'])->name('admin.establishments.reviews.sync-google');
        Route::get('/admin/establishments/{establishment}/hosts/create', [AdminEstablishmentController::class, 'createHost'])->name('admin.establishments.hosts.create');
        Route::post('/admin/establishments/{establishment}/hosts', [AdminEstablishmentController::class, 'storeHost'])->name('admin.establishments.hosts.store');
        Route::delete('/admin/establishments/{establishment}/hosts/{host}', [AdminEstablishmentController::class, 'destroyHost'])->name('admin.establishments.hosts.destroy');
        Route::get('/admin/properties', [\App\Http\Controllers\AdminPropertyController::class, 'index'])->name('admin.properties.index');
        Route::get('/admin/properties/create', [\App\Http\Controllers\AdminPropertyController::class, 'create'])->name('admin.properties.create');
        Route::post('/admin/properties', [\App\Http\Controllers\AdminPropertyController::class, 'store'])->name('admin.properties.store');
        Route::get('/admin/properties/{property}/edit', [\App\Http\Controllers\AdminPropertyController::class, 'edit'])->name('admin.properties.edit');
        Route::post('/admin/properties/{property}/improve-copy', [\App\Http\Controllers\AdminPropertyController::class, 'improveCopy'])->name('admin.properties.improve-copy');
        Route::put('/admin/properties/{property}', [\App\Http\Controllers\AdminPropertyController::class, 'update'])->name('admin.properties.update');
        Route::put('/admin/properties/{property}/amenities', [\App\Http\Controllers\AdminPropertyController::class, 'updateAmenities'])->name('admin.properties.amenities.update');
        Route::put('/admin/properties/{property}/images', [\App\Http\Controllers\AdminPropertyController::class, 'updateImages'])->name('admin.properties.images.update');
        Route::post('/admin/properties/{property}/images', [\App\Http\Controllers\AdminPropertyController::class, 'uploadImages'])->name('admin.properties.images.upload');
        Route::get('/admin/properties/{property}/price-rules/create', [\App\Http\Controllers\AdminPropertyController::class, 'createPriceRule'])->name('admin.properties.price-rules.create');
        Route::post('/admin/properties/{property}/price-rules', [\App\Http\Controllers\AdminPropertyController::class, 'storePriceRule'])->name('admin.properties.price-rules.store');
        Route::post('/admin/properties/{property}/features', [\App\Http\Controllers\AdminPropertyController::class, 'storePropertyFeature'])->name('admin.properties.features.store');
        Route::put('/admin/properties/{property}/features/{feature}', [\App\Http\Controllers\AdminPropertyController::class, 'updatePropertyFeature'])->name('admin.properties.features.update');
        Route::delete('/admin/properties/{property}/features/{feature}', [\App\Http\Controllers\AdminPropertyController::class, 'deletePropertyFeature'])->name('admin.properties.features.destroy');
        Route::get('/admin/properties/{property}/availability-blocks/create', [\App\Http\Controllers\AdminPropertyController::class, 'createAvailabilityBlock'])->name('admin.properties.availability.create');
        Route::post('/admin/properties/{property}/availability-blocks', [\App\Http\Controllers\AdminPropertyController::class, 'storeAvailabilityBlock'])->name('admin.properties.availability.store');
        Route::delete('/admin/properties/{property}/availability-blocks/{block}', [\App\Http\Controllers\AdminPropertyController::class, 'deleteAvailabilityBlock'])->name('admin.properties.availability.destroy');
        Route::get('/admin/properties/{property}/availability', [\App\Http\Controllers\AdminPropertyController::class, 'availabilityCheck'])->name('admin.properties.availability.check');
        Route::get('/admin/properties/{property}/calendar', [\App\Http\Controllers\AdminCalendarController::class, 'index'])->name('admin.properties.calendar');
        Route::get('/admin/properties/{property}/calendar/feeds/create', [\App\Http\Controllers\AdminCalendarController::class, 'createFeed'])->name('admin.properties.calendar.create');
        Route::post('/admin/properties/{property}/calendar/feeds', [\App\Http\Controllers\AdminCalendarController::class, 'storeFeed'])->name('admin.properties.calendar.store');
        Route::get('/admin/properties/{property}/calendar/feeds/{feed}/edit', [\App\Http\Controllers\AdminCalendarController::class, 'editFeed'])->name('admin.properties.calendar.edit');
        Route::put('/admin/properties/{property}/calendar/feeds/{feed}', [\App\Http\Controllers\AdminCalendarController::class, 'updateFeed'])->name('admin.properties.calendar.update');
        Route::delete('/admin/properties/{property}/calendar/feeds/{feed}', [\App\Http\Controllers\AdminCalendarController::class, 'deleteFeed'])->name('admin.properties.calendar.destroy');
        Route::post('/admin/properties/{property}/calendar/feeds/{feed}/sync', [\App\Http\Controllers\AdminCalendarController::class, 'syncFeed'])->name('admin.properties.calendar.sync');
        Route::get('/admin/properties/{property}/calendar/export.ics', [\App\Http\Controllers\AdminCalendarController::class, 'export'])->name('admin.properties.calendar.export');
    });

    Route::middleware('admin')->group(function () {
        Route::get('/admin/audit-logs', [\App\Http\Controllers\AdminAuditLogController::class, 'index'])->name('admin.audit-logs');
        Route::get('/admin/logs', [AdminLogController::class, 'index'])->name('admin.logs');
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users.index');
        Route::get('/admin/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::get('/admin/users/{managedUser}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/admin/users/{managedUser}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/admin/users/{managedUser}', [AdminController::class, 'deleteUser'])->name('admin.users.destroy');
        Route::get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
        Route::get('/admin/amenities', [\App\Http\Controllers\AdminAmenityController::class, 'index'])->name('admin.amenities.index');
        Route::post('/admin/amenities/categories', [\App\Http\Controllers\AdminAmenityController::class, 'storeCategory'])->name('admin.amenities.categories.store');
        Route::put('/admin/amenities/categories/{amenityCategory}', [\App\Http\Controllers\AdminAmenityController::class, 'updateCategory'])->name('admin.amenities.categories.update');
        Route::delete('/admin/amenities/categories/{amenityCategory}', [\App\Http\Controllers\AdminAmenityController::class, 'destroyCategory'])->name('admin.amenities.categories.destroy');
        Route::post('/admin/amenities/categories/{amenityCategory}/amenities', [\App\Http\Controllers\AdminAmenityController::class, 'storeAmenity'])->name('admin.amenities.store');
        Route::put('/admin/amenities/{amenity}', [\App\Http\Controllers\AdminAmenityController::class, 'updateAmenity'])->name('admin.amenities.update');
        Route::delete('/admin/amenities/{amenity}', [\App\Http\Controllers\AdminAmenityController::class, 'destroyAmenity'])->name('admin.amenities.destroy');
        Route::get('/admin/reviews', [AdminController::class, 'reviews'])->name('admin.reviews');
        Route::post('/admin/reviews', [AdminController::class, 'storeReview'])->name('admin.reviews.store');
        Route::put('/admin/reviews/{review}', [AdminController::class, 'updateReview'])->name('admin.reviews.update');
        Route::delete('/admin/reviews/{review}', [AdminController::class, 'deleteReview'])->name('admin.reviews.destroy');
        Route::post('/admin/reviews/import', [AdminController::class, 'importReviews'])->name('admin.reviews.import');
    });

    Route::middleware('platform-admin')->group(function () {
        Route::get('/platform/tenants', [\App\Http\Controllers\PlatformAdminController::class, 'index'])->name('platform.tenants.index');
        Route::get('/platform/tenants/create', [\App\Http\Controllers\PlatformAdminController::class, 'create'])->name('platform.tenants.create');
        Route::post('/platform/tenants', [\App\Http\Controllers\PlatformAdminController::class, 'store'])->name('platform.tenants.store');
        Route::post('/platform/tenants/{tenant}/suspend', [\App\Http\Controllers\PlatformAdminController::class, 'suspend'])->name('platform.tenants.suspend');
        Route::post('/platform/tenants/{tenant}/activate', [\App\Http\Controllers\PlatformAdminController::class, 'activate'])->name('platform.tenants.activate');
    });
});

Route::middleware('locale')->group(function () {
    Route::get('/properties', [PublicPropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/compare', [PublicPropertyController::class, 'compare'])->name('properties.compare');
    Route::get('/properties/{property:slug}', [PublicPropertyController::class, 'show'])->name('properties.show');
    Route::post('/properties/{property:slug}/availability', [PublicPropertyController::class, 'availability'])->name('properties.availability');
    Route::post('/properties/{property:slug}/reserve', [PublicPropertyController::class, 'reserve'])->name('properties.reserve');
    Route::get('/contact', [\App\Http\Controllers\StaticPageController::class, 'contact'])->name('contact');
    Route::get('/about', [\App\Http\Controllers\StaticPageController::class, 'about'])->name('about');
    Route::get('/reviews', [\App\Http\Controllers\StaticPageController::class, 'reviews'])->name('reviews');
    Route::get('/faq', [\App\Http\Controllers\StaticPageController::class, 'faq'])->name('faq');
    Route::get('/privacy', [\App\Http\Controllers\StaticPageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [\App\Http\Controllers\StaticPageController::class, 'terms'])->name('terms');
    Route::get('/cookies', [\App\Http\Controllers\StaticPageController::class, 'cookies'])->name('cookies');
});

Route::get('/sitemap.xml', function () {
    $urls = collect([
        route('home'),
        route('properties.index'),
        route('about'),
        route('contact'),
        route('reviews'),
        route('faq'),
        route('privacy'),
        route('terms'),
        route('cookies'),
    ]);

    if (Schema::hasTable('properties')) {
        $urls = $urls->merge(Property::query()->published()->pluck('slug')->map(fn ($slug) => route('properties.show', ['property' => $slug])));
    }

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
    foreach ($urls->unique() as $url) {
        $xml .= '<url><loc>' . e($url) . '</loc></url>';
    }
    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/calendar/{property:slug}/{token}.ics', [\App\Http\Controllers\AdminCalendarController::class, 'publicExport'])->name('calendar.public-export');
Route::get('/cleaning-schedule/{token}', [CleaningScheduleController::class, 'publicSchedule'])->name('cleaning.public');
Route::get('/cleaning-schedule/{token}/pdf', [CleaningScheduleController::class, 'publicPdf'])->name('cleaning.public.pdf');
Route::post('/cleaning-schedule/{token}/visits/{visit}/status', [CleaningScheduleController::class, 'publicStatus'])->middleware('throttle:30,1')->name('cleaning.public.status');

Route::middleware('locale')->group(function () {
    Route::get('/reservations/access', [\App\Http\Controllers\CheckoutController::class, 'accessHelp'])->name('reservation.access');
    Route::get('/reservations/{reservation}/checkout', [\App\Http\Controllers\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/reservations/{reservation}/checkout', [\App\Http\Controllers\CheckoutController::class, 'start'])->name('checkout.start');
    Route::post('/reservations/{reservation}/checkout/paypal/create', [\App\Http\Controllers\CheckoutController::class, 'createPayPalOrder'])->name('checkout.paypal.create');
    Route::post('/reservations/{reservation}/checkout/paypal/capture', [\App\Http\Controllers\CheckoutController::class, 'capturePayPalOrder'])->name('checkout.paypal.capture');
    Route::post('/reservations/{reservation}/checkout/complete', [\App\Http\Controllers\CheckoutController::class, 'complete'])->name('checkout.complete');
    Route::post('/reservations/{reservation}/checkout/cancel', [\App\Http\Controllers\CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::post('/reservations/{reservation}/checkout/offline-proof', [\App\Http\Controllers\CheckoutController::class, 'submitOfflineProof'])->name('checkout.offline-proof');
    Route::get('/reservations/{reservation}/checkout/{provider}/return', [\App\Http\Controllers\CheckoutController::class, 'return'])->name('checkout.return');
});
Route::post('/webhooks/{provider}', [\App\Http\Controllers\CheckoutController::class, 'webhook'])->name('checkout.webhook');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'application' => 'afrikappart',
        'environment' => app()->environment(),
        'php_version' => PHP_VERSION,
        'timestamp_utc' => now('UTC')->toIso8601String(),
    ]);
})->name('health');

Route::fallback(fn () => abort(404))->middleware('locale');
