<?php

use App\Http\Controllers\Driver\HomeController as DriverHomeController;
use App\Http\Controllers\Driver\VehicleController;
use App\Http\Controllers\Driver\TripController;
use App\Http\Controllers\AttractionController;
use App\Http\Controllers\Passenger\HomeController as PassengerHomeController;
use App\Http\Controllers\Passenger\PassengerBookingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attractions', [AttractionController::class, 'index'])
        ->name('attractions.index');
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

    Route::middleware('driver')->prefix('driver')->name('driver.')->group(function () {
        Route::get('trips/journey', [TripController::class, 'journey'])->name('trips.journey');
        Route::get('trips/history', [TripController::class, 'history'])->name('trips.history');
        Route::patch('trips/{trip}/start', [TripController::class, 'start'])->name('trips.start');
        Route::patch('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');
        Route::patch('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
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
