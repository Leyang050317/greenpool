<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $activeTrip = $user->trips()
            ->with([
                'vehicle',
                'bookings' => fn ($booking) => $booking
                    ->with('passenger')
                    ->where('booking_status', 'Accepted')
                    ->oldest('pickup_sequence')
                    ->oldest(),
            ])
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->where('status', 'In Progress')
            ->oldest('departure_at')
            ->first();

        $pendingRequests = Booking::query()
            ->with(['passenger', 'trip.vehicle'])
            ->where('booking_status', 'Pending')
            ->whereHas('trip', fn ($trip) => $trip
                ->where('user_id', $user->id)
                ->where('status', 'Scheduled'))
            ->latest()
            ->limit(5)
            ->get();

        $pendingRequestsCount = Booking::query()
            ->where('booking_status', 'Pending')
            ->whereHas('trip', fn ($trip) => $trip
                ->where('user_id', $user->id)
                ->where('status', 'Scheduled'))
            ->count();

        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(5)
            ->get();

        $todaySchedule = $user->trips()
            ->with('vehicle')
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->whereDate('departure_at', today())
            ->oldest('departure_at')
            ->get();

        $totalEarnings = Booking::query()
            ->join('trips', 'bookings.trip_id', '=', 'trips.trip_id')
            ->where('trips.user_id', $user->id)
            ->where('trips.status', 'Completed')
            ->where('bookings.booking_status', 'Accepted')
            ->sum(DB::raw('bookings.number_of_seats * trips.price_per_passenger'));

        $driverStats = [
            'total_trips' => $user->trips()->count(),
            'completed_trips' => $user->trips()->where('status', 'Completed')->count(),
            'pending_requests' => $pendingRequestsCount,
            'total_earnings' => $totalEarnings,
        ];

        return view('driver.home', compact(
            'activeTrip',
            'pendingRequests',
            'recentNotifications',
            'todaySchedule',
            'driverStats'
        ));
    }
}
