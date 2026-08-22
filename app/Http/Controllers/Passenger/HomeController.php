<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $pendingRatingBooking = Booking::query()
            ->with(['trip.user'])
            ->where('passenger_id', $user->id)
            ->where('booking_status', 'Accepted')
            ->whereHas('trip', fn ($trip) => $trip
                ->where('status', 'Completed')
                ->where('completed_at', '>=', now()->subDays(7)))
            ->whereDoesntHave('ratings', fn ($rating) => $rating->where('reviewer_id', $user->id))
            ->latest('updated_at')
            ->first();

        $upcomingRide = Booking::query()
            ->with(['trip.user', 'trip.vehicle'])
            ->where('passenger_id', $user->id)
            ->where('booking_status', 'Accepted')
            ->whereHas('trip', fn ($trip) => $trip
                ->whereIn('status', ['Scheduled', 'In Progress'])
                ->where('departure_at', '>=', now()->subHours(6)))
            ->orderBy(
                Trip::query()
                    ->select('departure_at')
                    ->whereColumn('trips.trip_id', 'bookings.trip_id')
            )
            ->first();

        $pendingRequests = Booking::query()
            ->with(['trip.user', 'trip.vehicle'])
            ->where('passenger_id', $user->id)
            ->where('booking_status', 'Pending')
            ->whereHas('trip', fn ($trip) => $trip
                ->where('status', 'Scheduled')
                ->where('departure_at', '>=', now()))
            ->orderBy(
                Trip::query()
                    ->select('departure_at')
                    ->whereColumn('trips.trip_id', 'bookings.trip_id')
            )
            ->limit(3)
            ->get();

        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(5)
            ->get();

        $rideStats = [
            'total' => $user->bookings()->count(),
            'completed' => $user->bookings()
                ->where('booking_status', 'Accepted')
                ->whereHas('trip', fn ($trip) => $trip->where('status', 'Completed'))
                ->count(),
            'pending' => $user->bookings()->where('booking_status', 'Pending')->count(),
            'cancelled_rejected' => $user->bookings()
                ->whereIn('booking_status', ['Cancelled', 'Rejected'])
                ->count(),
        ];

        return view('passenger.home', compact(
            'pendingRatingBooking',
            'upcomingRide',
            'pendingRequests',
            'recentNotifications',
            'rideStats'
        ));
    }
}
