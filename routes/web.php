<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Passenger\PassengerBookingController;
use App\Http\Controllers\Passenger\HomeController as PassengerHomeController;
use App\Http\Controllers\Driver\HomeController as DriverHomeController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/passenger/home', [PassengerHomeController::class, 'index'])
        ->name('passenger.home');

    Route::get('/driver/home', [DriverHomeController::class, 'index'])
        ->name('driver.home');

    Route::get('/passenger/booking', [PassengerBookingController::class, 'index'])
    ->name('passenger.booking');

});

Route::view('/email-verified', 'auth.verification-success')
    ->name('verification.success');

require __DIR__.'/auth.php';
