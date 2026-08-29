<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly bool $departureTimeChanged = false,
        private readonly array $changedAttributes = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing('trip.vehicle');
        $trip = $this->booking->trip;
        $locationsChanged = (bool) array_intersect($this->changedAttributes, ['departure_location', 'destination']);

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $trip->trip_id,
            'type' => 'trip_updated',
            'title' => $this->departureTimeChanged ? 'Trip Departure Time Updated' : 'Your trip was updated',
            'icon' => 'route',
            'message' => $this->departureTimeChanged
                ? 'The driver has changed the departure time of your trip to '.$trip->departure_at->format('g:i A').'.'
                : 'Your driver updated the trip from '.$trip->departure_location.' to '.$trip->destination.'.',
            ...($this->departureTimeChanged ? [
                'departure_at' => $trip->departure_at->toIso8601String(),
                'departure_at_label' => $trip->departure_at->format('d M Y, g:i A'),
            ] : []),
            'trip_details' => [
                'departure_location' => $trip->departure_location,
                'destination' => $trip->destination,
                'vehicle_label' => trim(($trip->vehicle?->brand ?? '').' '.($trip->vehicle?->model ?? '')) ?: 'Vehicle details unavailable',
            ],
            'locations_changed' => $locationsChanged,
            'url' => route('passenger.bookings.show', $this->booking),
        ];
    }
}
