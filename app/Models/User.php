<?php

namespace App\Models;

use App\Models\Concerns\HasProfileCompleteness;
use App\Notifications\GreenPoolVerifyEmail;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasProfileCompleteness, Notifiable {
        Notifiable::notify as protected notifyThroughLaravel;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'auth_provider',
        'google_id',
        'role',
        'photo',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function driverPreference(): HasOne
    {
        return $this->hasOne(DriverPreference::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Deliver optional in-app notifications only when the recipient enabled
     * the relevant category in Settings. Safety and account emails bypass it.
     */
    public function notify($instance): void
    {
        if ($instance instanceof Notification && ! $this->allowsInAppNotification($instance)) {
            return;
        }

        $this->notifyThroughLaravel($instance);
    }

    public function allowsInAppNotification(Notification $notification): bool
    {
        $preferenceField = match ($notification::class) {
            \App\Notifications\TripUpdatedNotification::class,
            \App\Notifications\TripReminderNotification::class => 'trip_updates',
            \App\Notifications\BookingRequestNotification::class,
            \App\Notifications\BookingStatusNotification::class => 'booking_updates',
            \App\Notifications\PaymentDueNotification::class,
            \App\Notifications\PaymentCompletedNotification::class,
            \App\Notifications\PaymentReceivedNotification::class,
            \App\Notifications\CashPaymentSelectedNotification::class => 'payment_updates',
            \App\Notifications\MessageReceivedNotification::class => 'message_alerts',
            \App\Notifications\RatingReminderNotification::class,
            \App\Notifications\RatingReceivedNotification::class => 'rating_reminders',
            default => null,
        };

        if ($preferenceField === null) {
            return true;
        }

        $preferences = $this->notificationPreference()->firstOrCreate([]);

        return (bool) $preferences->{$preferenceField};
    }

    public function driverLicence(): HasOne
    {
        return $this->hasOne(DriverLicence::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function usesGoogleAuthentication(): bool
    {
        return $this->auth_provider === 'google';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new GreenPoolVerifyEmail());
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(Favourite::class);
    }

    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'reviewer_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'reviewee_id');
    }

    public function paymentsMade(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_id');
    }

    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'payee_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function emergencies(): HasMany
    {
        return $this->hasMany(Emergency::class);
    }
}
