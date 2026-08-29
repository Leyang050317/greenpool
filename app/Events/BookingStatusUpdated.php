<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $driverNotificationType = null,
        public ?string $passengerNotificationType = null,
        public ?int $pickedUpBookingId = null,
    )
    {
        $this->booking->loadMissing(['trip']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('passenger.'.$this->booking->passenger_id),
            new PrivateChannel('driver.'.$this->booking->trip->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        $pickupProgress = $this->booking->trip->bookings()
            ->where('booking_status', 'Accepted')
            ->orderByRaw('case when pickup_sequence is null then 1 else 0 end')
            ->orderBy('pickup_sequence')
            ->oldest()
            ->get(['id', 'pickup_sequence', 'picked_up_at'])
            ->map(fn (Booking $booking) => [
                'booking_id' => $booking->id,
                'pickup_sequence' => $booking->pickup_sequence,
                'picked_up_at' => $booking->picked_up_at?->toIso8601String(),
            ])->all();
        return [
            'booking_id' => $this->booking->id,
            'status' => $this->booking->booking_status,
            'trip_status' => $this->booking->trip->status,
            'driver_id' => $this->booking->trip->user_id,
            'trip_id' => $this->booking->trip_id,
            'trip_route' => $this->booking->trip->departure_location.' → '.$this->booking->trip->destination,
            'passenger_url' => route('passenger.bookings.show', $this->booking),
            'driver_url' => route('driver.booking-requests.show', $this->booking),
            'driver_notification_type' => $this->driverNotificationType,
            'passenger_notification_type' => $this->passengerNotificationType,
            'updated_at' => $this->booking->updated_at?->toIso8601String(),
            'pickup_progress' => $pickupProgress,
            'picked_up_booking_id' => $this->pickedUpBookingId,
        ];
    }
}
