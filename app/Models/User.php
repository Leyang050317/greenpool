<?php

namespace App\Models;

use App\Models\Concerns\HasProfileCompleteness;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\BookingRequestNotification;
use App\Notifications\BookingStatusNotification;
use App\Notifications\CashPaymentSelectedNotification;
use App\Notifications\GreenPoolResetPassword;
use App\Notifications\GreenPoolVerifyEmail;
use App\Notifications\MessageReceivedNotification;
use App\Notifications\PaymentCompletedNotification;
use App\Notifications\PaymentDueNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RatingReceivedNotification;
use App\Notifications\RatingReminderNotification;
use App\Notifications\TripAutoCancelledNotification;
use App\Notifications\TripReminderNotification;
use App\Notifications\TripUpdatedNotification;
use App\Notifications\TripUpdateNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasProfileCompleteness, Notifiable {
        Notifiable::notify as protected notifyThroughLaravel;
    }

    /** @var array<string, mixed> */
    protected $attributes = [
        'account_status' => 'active',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'telegram_chat_id',
        'password',
        'auth_provider',
        'google_id',
        'role',
        'photo',
        'phone_number',
        'account_status',
        'deactivated_at',
        'permanently_closed_at',
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
            'deactivated_at' => 'datetime',
            'permanently_closed_at' => 'datetime',
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
        $preferenceField = $notification instanceof BookingStatusNotification
            ? $notification->preferenceField()
            : match ($notification::class) {
                TripUpdatedNotification::class,
                TripUpdateNotification::class,
                TripReminderNotification::class,
                TripAutoCancelledNotification::class => 'trip_updates',
                BookingRequestNotification::class => 'booking_updates',
                PaymentDueNotification::class,
                PaymentCompletedNotification::class,
                PaymentReceivedNotification::class,
                CashPaymentSelectedNotification::class => 'payment_updates',
                MessageReceivedNotification::class => 'message_alerts',
                RatingReminderNotification::class,
                RatingReceivedNotification::class => 'rating_reminders',
                default => null,
            };

        if ($preferenceField === null) {
            return true;
        }

        $preferences = $this->notificationPreference()->firstOrCreate([], [
            'trip_updates' => true,
            'booking_updates' => true,
            'payment_updates' => true,
            'message_alerts' => true,
            'rating_reminders' => true,
            'attraction_updates' => true,
        ]);

        return (bool) $preferences->{$preferenceField};
    }

    /** @return array<string, bool> */
    public function inAppNotificationPreferences(): array
    {
        $preferences = $this->notificationPreference()->first();

        return collect([
            'trip_updates',
            'booking_updates',
            'payment_updates',
            'message_alerts',
            'rating_reminders',
        ])->mapWithKeys(fn (string $field) => [
            $field => $preferences ? (bool) $preferences->{$field} : true,
        ])->all();
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

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isDeactivated(): bool
    {
        return $this->account_status === 'deactivated';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new GreenPoolVerifyEmail);
    }

    /**
     * Send the password reset notification using a branded GreenPool template.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new GreenPoolResetPassword($token));
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
