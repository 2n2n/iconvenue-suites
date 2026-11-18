<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// No public website (turned off 2026-11-18, docs/decisions/2026-11-18-disable-public-website.md).
// The home page is the staff sign-in; the guest middleware on /login sends signed-in users to the dashboard.
Route::redirect('/', '/login')->name('home');

// Auth Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin & Staff Routes
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Management
    Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/image', [\App\Http\Controllers\Admin\ProfileController::class, 'removeProfileImage'])->name('profile.removeImage');

    // Bookings (Staff & Admin)
    Route::resource('bookings', BookingController::class);
    Route::patch('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::patch('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('bookings/{booking}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
    Route::get('bookings-calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
    Route::get('bookings-calendar/data', [BookingController::class, 'calendarData'])->name('bookings.calendar.data');

    // Browse Venues & Suites (Staff & Admin)
    Route::get('venues/browse/all', [VenueController::class, 'browseVenues'])->name('venues.browse');
    Route::get('suites/browse/all', [VenueController::class, 'browseSuites'])->name('suites.browse');

    // Toggle Availability (Staff & Admin)
    Route::patch('venues/{venue}/toggle-availability', [VenueController::class, 'toggleVenueAvailability'])->name('venues.toggle-availability');
    Route::patch('venues/{venue}/packages/{package}/toggle-availability', [VenueController::class, 'togglePackageAvailability'])->name('venues.packages.toggle-availability');

    // Clients (Staff & Admin)
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/{email}', [ClientController::class, 'show'])->name('clients.show');

    // Payments (Staff & Admin)
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create/{booking}', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Admin Only Routes
    Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->group(function () {
        // Client details (admin only; staff can list and view clients)
        Route::put('clients/{email}', [ClientController::class, 'update'])->name('clients.update');

        // Venues Management
        Route::get('venues', [VenueController::class, 'indexVenues'])->name('venues.index');
        Route::get('venues/create', [VenueController::class, 'createVenue'])->name('venues.create');
        Route::post('venues', [VenueController::class, 'storeVenue'])->name('venues.store');
        Route::get('venues/{venue}/edit', [VenueController::class, 'edit'])->name('venues.edit');
        Route::put('venues/{venue}', [VenueController::class, 'update'])->name('venues.update');
        Route::delete('venues/{venue}', [VenueController::class, 'destroy'])->name('venues.destroy');

        // Suites Management
        Route::get('suites', [VenueController::class, 'indexSuites'])->name('suites.index');
        Route::get('suites/create', [VenueController::class, 'createSuite'])->name('suites.create');
        Route::post('suites', [VenueController::class, 'storeSuite'])->name('suites.store');
        Route::get('suites/{venue}/edit', [VenueController::class, 'editSuite'])->name('suites.edit');
        Route::put('suites/{venue}', [VenueController::class, 'updateSuite'])->name('suites.update');
        Route::delete('suites/{venue}', [VenueController::class, 'destroySuite'])->name('suites.destroy');

        // Room Rates: every suite's regular + per-platform rates on one grid (admin only)
        Route::get('rates', [\App\Http\Controllers\Admin\RoomRateController::class, 'index'])->name('rates.index');
        Route::put('rates', [\App\Http\Controllers\Admin\RoomRateController::class, 'update'])->name('rates.update');
        // Old per-suite Rates page → grid at that room
        Route::get('suites/{venue}/rates', [\App\Http\Controllers\Admin\RoomRateController::class, 'legacySuiteRates'])->name('suites.rates.edit');

        // Booking platforms (Agoda, Booking.com, …) — admin only
        Route::get('booking-platforms', [\App\Http\Controllers\Admin\BookingPlatformController::class, 'index'])->name('booking-platforms.index');
        Route::post('booking-platforms', [\App\Http\Controllers\Admin\BookingPlatformController::class, 'store'])->name('booking-platforms.store');
        Route::put('booking-platforms/{platform}', [\App\Http\Controllers\Admin\BookingPlatformController::class, 'update'])->name('booking-platforms.update');
        Route::patch('booking-platforms/{platform}/toggle', [\App\Http\Controllers\Admin\BookingPlatformController::class, 'toggle'])->name('booking-platforms.toggle');
        Route::delete('booking-platforms/{platform}', [\App\Http\Controllers\Admin\BookingPlatformController::class, 'destroy'])->name('booking-platforms.destroy');

        Route::resource('staff', StaffController::class);
        Route::patch('staff/{staff}/toggle', [StaffController::class, 'toggleActive'])->name('staff.toggle');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        // Carousel Management
        Route::resource('carousel', \App\Http\Controllers\Admin\CarouselController::class);
        Route::patch('carousel/{carousel}/toggle', [\App\Http\Controllers\Admin\CarouselController::class, 'toggleActive'])->name('carousel.toggle');

        // Venue Packages Management
        Route::get('venues/{venue}/packages', [\App\Http\Controllers\Admin\VenueController::class, 'packages'])->name('venues.packages');
        Route::get('venues/{venue}/packages/{package}/data', [\App\Http\Controllers\Admin\VenueController::class, 'getPackageData'])->name('venues.packages.data');
        Route::post('venues/{venue}/packages', [\App\Http\Controllers\Admin\VenueController::class, 'storePackage'])->name('venues.packages.store');
        Route::put('venues/{venue}/packages/{package}', [\App\Http\Controllers\Admin\VenueController::class, 'updatePackage'])->name('venues.packages.update');
        Route::delete('venues/{venue}/packages/{package}', [\App\Http\Controllers\Admin\VenueController::class, 'destroyPackage'])->name('venues.packages.destroy');
        Route::patch('venues/{venue}/packages/{package}/toggle', [\App\Http\Controllers\Admin\VenueController::class, 'togglePackage'])->name('venues.packages.toggle');

        // Remove venue/suite image
        Route::post('venues/{venue}/remove-image', [\App\Http\Controllers\Admin\VenueController::class, 'removeImage'])->name('venues.removeImage');
        Route::post('suites/{venue}/remove-image', [\App\Http\Controllers\Admin\VenueController::class, 'removeImage'])->name('suites.removeImage');

        // Add-ons Management
        Route::resource('addons', \App\Http\Controllers\Admin\VenueAddonController::class);
        Route::patch('addons/{addon}/toggle', [\App\Http\Controllers\Admin\VenueAddonController::class, 'toggleActive'])->name('addons.toggle');
        Route::post('addons/bulk-action', [\App\Http\Controllers\Admin\VenueAddonController::class, 'bulkAction'])->name('addons.bulk-action');
    });
});
