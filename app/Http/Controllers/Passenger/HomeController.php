<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $pendingRatingBooking = Booking::query()
            ->with(['trip.user'])
            ->where('passenger_id', $request->user()->id)
            ->where('booking_status', 'Accepted')
            ->whereHas('trip', fn ($trip) => $trip
                ->where('status', 'Completed')
                ->where('completed_at', '>=', now()->subDays(7)))
            ->whereDoesntHave('ratings', fn ($rating) => $rating->where('reviewer_id', $request->user()->id))
            ->latest('updated_at')
            ->first();

        return view('passenger.home', compact('pendingRatingBooking'));
    }
}
