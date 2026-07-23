<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Passenger\HomeController as PassengerHomeController;
use App\Http\Controllers\Driver\HomeController as DriverHomeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

});

Route::view('/email-verified', 'auth.verification-success')
    ->name('verification.success');

require __DIR__.'/auth.php';
