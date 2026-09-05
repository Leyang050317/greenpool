<?php

use App\Http\Controllers\AttractionController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\LinkedAccountController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Driver\DriverBookingController;
use App\Http\Controllers\Driver\HomeController as DriverHomeController;
use App\Http\Controllers\Driver\ProfileController as DriverProfileController;
use App\Http\Controllers\Driver\TripController;
use App\Http\Controllers\Driver\TripLocationController;
use App\Http\Controllers\Driver\VehicleController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\FaqBotController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Passenger\HomeController as PassengerHomeController;
use App\Http\Controllers\Passenger\PassengerBookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TripUpdateController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->role === 'driver' ? 'driver.home' : 'passenger.home');
    }

    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/account/reactivate', [ProfileController::class, 'showReactivation'])->name('account.reactivate.show');
    Route::post('/account/reactivate', [ProfileController::class, 'reactivate'])->name('account.reactivate');
    Route::get('/account/deactivate/google', [GoogleLoginController::class, 'redirectToGoogleForDeactivation'])->name('account.deactivate.google');
});

Route::middleware(['auth', 'active-account'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/telegram/link', [TelegramWebhookController::class, 'redirect'])
        ->middleware('throttle:6,1')
        ->name('telegram.link');
    Route::delete('/telegram/link', [TelegramWebhookController::class, 'unlink'])
        ->name('telegram.unlink');
    Route::post('/phone-verification/send', [PhoneVerificationController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('phone-verification.send');
    Route::post('/phone-verification/verify', [PhoneVerificationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('phone-verification.verify');
    Route::post('/emergency-contacts', [EmergencyContactController::class, 'store'])
        ->name('emergency-contacts.store');
    Route::patch('/emergency-contacts/{emergencyContact}', [EmergencyContactController::class, 'update'])
        ->name('emergency-contacts.update');
    Route::delete('/emergency-contacts/{emergencyContact}', [EmergencyContactController::class, 'destroy'])
        ->name('emergency-contacts.destroy');
    Route::get('/linked-accounts/google', [LinkedAccountController::class, 'redirectToGoogle'])
        ->name('linked-accounts.google.redirect');
    Route::delete('/linked-accounts/google', [LinkedAccountController::class, 'destroy'])
        ->name('linked-accounts.google.destroy');
});

Route::middleware(['auth', 'active-account', 'verified'])->group(function () {
    Route::get('/faq-bot/featured', [FaqBotController::class, 'featured'])->middleware('throttle:30,1')->name('faq-bot.featured');
    Route::post('/faq-bot/answer', [FaqBotController::class, 'answer'])->middleware('throttle:30,1')->name('faq-bot.answer');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
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

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/bookings/{booking}/checkout', [PaymentController::class, 'checkout'])->name('payments.checkout');
    Route::post('/payments/bookings/{booking}/checkout', [PaymentController::class, 'initiateCheckout'])->name('payments.checkout.initiate');
    Route::get('/payments/stripe/success', [PaymentController::class, 'stripeSuccess'])->name('payments.stripe.success');
    Route::post('/payments/{payment}/cash/confirm', [PaymentController::class, 'confirmCash'])->name('payments.cash.confirm');
    Route::post('/payments/{payment}/issue', [PaymentController::class, 'reportIssue'])->name('payments.issue.report');
    Route::post('/payments/{payment}/issue/resolve', [PaymentController::class, 'resolveIssue'])->name('payments.issue.resolve');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    Route::get('/messages', [ChatController::class, 'index'])->name('messages.index');
    Route::get('/bookings/{booking}/chat', [ChatController::class, 'show'])->name('bookings.chat.show');
    Route::post('/bookings/{booking}/chat', [ChatController::class, 'store'])->name('bookings.chat.store');

    Route::get('/attractions', [AttractionController::class, 'index'])
        ->name('attractions.index');
    Route::get('/attractions/autocomplete', [AttractionController::class, 'autocomplete'])
        ->name('attractions.autocomplete');
    Route::get('/attractions/google-place', [AttractionController::class, 'openGooglePlace'])
        ->name('attractions.google-place');
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
    Route::get('/passenger/booking/locations/autocomplete', [PassengerBookingController::class, 'autocomplete'])
        ->name('passenger.bookings.locations.autocomplete');
    Route::get('/passenger/booking/locations/current', [PassengerBookingController::class, 'currentLocation'])
        ->name('passenger.bookings.locations.current');
    Route::post('/passenger/booking', [PassengerBookingController::class, 'store'])
        ->name('passenger.bookings.store');
    Route::get('/passenger/booking/history', [PassengerBookingController::class, 'history'])
        ->name('passenger.bookings.history');
    Route::get('/passenger/bookings/{booking}', [PassengerBookingController::class, 'show'])
        ->name('passenger.bookings.show');
    Route::patch('/passenger/booking/{booking}/cancel', [PassengerBookingController::class, 'cancel'])
        ->name('passenger.bookings.cancel');
    Route::post('/trips/{trip}/emergencies', [EmergencyController::class, 'store'])->name('trips.emergencies.store');
    Route::post('/trips/{trip}/updates', [TripUpdateController::class, 'store'])->name('trips.updates.store');
    Route::patch('/emergencies/{emergency}/acknowledge', [EmergencyController::class, 'acknowledge'])->name('emergencies.acknowledge');
    Route::patch('/emergencies/{emergency}/resolve', [EmergencyController::class, 'resolve'])->name('emergencies.resolve');

    Route::middleware('driver')->prefix('driver')->name('driver.')->group(function () {
        Route::get('profile', [DriverProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [DriverProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/preferences', [DriverProfileController::class, 'updatePreferences'])
            ->name('profile.preferences.update');
        Route::put('profile/driving-licence', [DriverProfileController::class, 'updateLicence'])
            ->name('profile.driving-licence.update');
        Route::get('profile/driving-licence/image', [DriverProfileController::class, 'licenceImage'])
            ->name('profile.driving-licence.image');
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
        Route::patch('trips/{trip}/emergency-cancel', [TripController::class, 'emergencyCancel'])->name('trips.emergency-cancel');
        Route::post('trips/{trip}/location', [TripLocationController::class, 'store'])->middleware('throttle:15,1')->name('trips.location.store');
        Route::get('trips/locations/autocomplete', [TripController::class, 'autocomplete'])->name('trips.locations.autocomplete');
        Route::get('trips/fare-estimate', [TripController::class, 'fareEstimate'])->name('trips.fare-estimate');
        Route::resource('trips', TripController::class);
        Route::patch('vehicles/{vehicle}/activate', [VehicleController::class, 'activate'])
            ->name('vehicles.activate');
        Route::patch('vehicles/{vehicle}/deactivate', [VehicleController::class, 'deactivate'])
            ->name('vehicles.deactivate');
        Route::post('vehicles/documents/ocr', [VehicleController::class, 'ocr'])
            ->middleware('throttle:10,1')
            ->name('vehicles.documents.ocr');
        Route::post('vehicles/images/validate', [VehicleController::class, 'validateImage'])
            ->middleware('throttle:12,1')
            ->name('vehicles.images.validate');
        Route::post('vehicles/plate-availability', [VehicleController::class, 'plateAvailability'])
            ->middleware('throttle:30,1')
            ->name('vehicles.plate-availability');
        Route::get('vehicles/archived', [VehicleController::class, 'archived'])
            ->name('vehicles.archived');
        Route::patch('vehicles/archived/{vehicle}/restore', [VehicleController::class, 'restore'])
            ->name('vehicles.restore');
        Route::resource('vehicles', VehicleController::class);
    });

});

require __DIR__.'/auth.php';

Route::get('/auth/google', [GoogleLoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::get('/auth/google/role', [GoogleLoginController::class, 'showRoleSelection'])->name('auth.google.role');
Route::post('/auth/google/role', [GoogleLoginController::class, 'storeRole'])->name('auth.google.storeRole');
