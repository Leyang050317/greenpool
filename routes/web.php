<?php

use App\Http\Controllers\AttractionController;
use App\Http\Controllers\Driver\DriverBookingController;
use App\Http\Controllers\Driver\HomeController as DriverHomeController;
use App\Http\Controllers\Driver\TripController;
use App\Http\Controllers\Driver\VehicleController;
use App\Http\Controllers\Passenger\HomeController as PassengerHomeController;
use App\Http\Controllers\Passenger\PassengerBookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->role === 'driver' ? 'driver.home' : 'passenger.home');
    }

    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ratings', [RatingController::class, 'hub'])->name('ratings.index');
    Route::get('/ratings/history', [RatingController::class, 'index'])->name('ratings.history');
    Route::get('/ratings/people', [RatingController::class, 'people'])->name('ratings.people');
    Route::get('/ratings/reviews', [RatingController::class, 'reviews'])->name('ratings.reviews');
    Route::get('/ratings/pending', [RatingController::class, 'pending'])->name('ratings.pending');
    Route::get('/ratings/received/{user}', [RatingController::class, 'received'])->name('ratings.received');
    Route::get('/ratings/bookings/{booking}/create', [RatingController::class, 'create'])->name('ratings.create');
    Route::post('/ratings/bookings/{booking}', [RatingController::class, 'store'])->name('ratings.store');
    Route::get('/ratings/{rating}/submitted', [RatingController::class, 'submitted'])->name('ratings.submitted');
    Route::get('/ratings/{rating}/edit', [RatingController::class, 'edit'])->name('ratings.edit');
    Route::patch('/ratings/{rating}', [RatingController::class, 'update'])->name('ratings.update');

    Route::get('/attractions', [AttractionController::class, 'index'])
        ->name('attractions.index');
    Route::get('/attractions/{attraction}/google-photo', [AttractionController::class, 'googlePhoto'])
        ->name('attractions.google-photo');
    Route::get('/attractions/{attraction}/google-card-photo', [AttractionController::class, 'googleCardPhoto'])
        ->name('attractions.google-card-photo');
    Route::get('/attractions/{attraction}', [AttractionController::class, 'show'])
        ->name('attractions.show');
    Route::post('/attractions/{attraction}/favourites', [AttractionController::class, 'storeFavourite'])
        ->name('attractions.favourites.store');
    Route::delete('/attractions/{attraction}/favourites', [AttractionController::class, 'destroyFavourite'])
        ->name('attractions.favourites.destroy');

    Route::get('/passenger/home', [PassengerHomeController::class, 'index'])
        ->name('passenger.home');

    Route::get('/driver/home', [DriverHomeController::class, 'index'])
        ->name('driver.home');

    Route::get('/passenger/booking', [PassengerBookingController::class, 'index'])
        ->name('passenger.booking');
    Route::get('/passenger/booking/create', [PassengerBookingController::class, 'create'])
        ->name('passenger.bookings.create');
    Route::post('/passenger/booking', [PassengerBookingController::class, 'store'])
        ->name('passenger.bookings.store');
    Route::get('/passenger/booking/history', [PassengerBookingController::class, 'history'])
        ->name('passenger.bookings.history');
    Route::patch('/passenger/booking/{booking}/cancel', [PassengerBookingController::class, 'cancel'])
        ->name('passenger.bookings.cancel');

    Route::middleware('driver')->prefix('driver')->name('driver.')->group(function () {
        Route::get('booking', [DriverBookingController::class, 'index'])
            ->name('booking-requests.index');
        Route::get('booking/{booking}', [DriverBookingController::class, 'show'])
            ->name('booking-requests.show');
        Route::patch('booking/{booking}/accept', [DriverBookingController::class, 'accept'])
            ->name('booking-requests.accept');
        Route::patch('booking/{booking}/reject', [DriverBookingController::class, 'reject'])
            ->name('booking-requests.reject');
        Route::get('trips/journey', [TripController::class, 'journey'])->name('trips.journey');
        Route::get('trips/history', [TripController::class, 'history'])->name('trips.history');
        Route::patch('trips/{trip}/start', [TripController::class, 'start'])->name('trips.start');
        Route::patch('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');
        Route::patch('trips/{trip}/bookings/{booking}/pickup', [TripController::class, 'pickup'])->name('trips.bookings.pickup');
        Route::patch('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
        Route::get('trips/locations/autocomplete', [TripController::class, 'autocomplete'])->name('trips.locations.autocomplete');
        Route::resource('trips', TripController::class);
        Route::patch('vehicles/{vehicle}/activate', [VehicleController::class, 'activate'])
            ->name('vehicles.activate');
        Route::patch('vehicles/{vehicle}/deactivate', [VehicleController::class, 'deactivate'])
            ->name('vehicles.deactivate');
        Route::resource('vehicles', VehicleController::class);
    });

});

Route::view('/email-verified', 'auth.verification-success')
    ->name('verification.success');

require __DIR__.'/auth.php';

use App\Http\Controllers\Auth\GoogleLoginController;

Route::get('/auth/google', [GoogleLoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'handleGoogleCallback']);

Route::get('/auth/google/role', [App\Http\Controllers\Auth\GoogleLoginController::class, 'showRoleSelection'])->name('auth.google.role');
Route::post('/auth/google/role', [App\Http\Controllers\Auth\GoogleLoginController::class, 'storeRole'])->name('auth.google.storeRole');
