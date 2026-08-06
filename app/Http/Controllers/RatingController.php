<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRatingRequest;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function hub(Request $request): View
    {
        $targetRole = $request->user()->role === 'passenger' ? 'driver' : 'passenger';

        return view('ratings.hub', compact('targetRole'));
    }

    public function index(Request $request): View
    {
        $query = $request->user()->ratingsGiven()->with(['reviewee', 'booking.trip']);
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->whereHas('reviewee', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if (in_array($request->input('role'), ['driver', 'passenger'], true)) {
            $query->whereHas('reviewee', fn ($q) => $q->where('role', $request->input('role')));
        }
        $query->orderBy('created_at', $request->input('sort') === 'oldest' ? 'asc' : 'desc');
        $ratings = $query->paginate(8)->withQueryString();
        $stats = [
            'total' => $request->user()->ratingsGiven()->count(),
            'drivers' => $request->user()->ratingsGiven()->whereHas('reviewee', fn ($q) => $q->where('role', 'driver'))->count(),
            'passengers' => $request->user()->ratingsGiven()->whereHas('reviewee', fn ($q) => $q->where('role', 'passenger'))->count(),
        ];

        return view('ratings.index', compact('ratings', 'stats'));
    }

    public function people(Request $request): View
    {
        $user = $request->user();
        $targetRole = $user->role === 'passenger' ? 'driver' : 'passenger';
        $query = User::query()->where('role', $targetRole)
            ->withAvg('ratingsReceived', 'score')
            ->withCount('ratingsReceived');

        if ($user->role === 'passenger') {
            $query->whereHas('trips.bookings', fn ($booking) => $booking
                ->where('passenger_id', $user->id)
                ->where('booking_status', 'Accepted'));
        } else {
            $query->whereHas('bookings', fn ($booking) => $booking
                ->where('booking_status', 'Accepted')
                ->whereHas('trip', fn ($trip) => $trip->where('user_id', $user->id)));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
        }

        $people = $query->orderBy('name')->paginate(8)->withQueryString();

        return view('ratings.people', compact('people', 'targetRole'));
    }

    public function create(Request $request, Booking $booking): View
    {
        [$reviewee] = $this->ratingParticipants($request, $booking);
        abort_if($booking->ratings()->where('reviewer_id', $request->user()->id)->exists(), 409, 'You have already rated this booking.');

        return view('ratings.create', compact('booking', 'reviewee'));
    }

    public function pending(Request $request): View
    {
        $user = $request->user();
        $query = Booking::query()
            ->with(['passenger', 'trip.user'])
            ->where('booking_status', 'Accepted')
            ->whereHas('trip', fn ($trip) => $trip->where('status', 'Completed'))
            ->whereDoesntHave('ratings', fn ($rating) => $rating->where('reviewer_id', $user->id));

        if ($user->role === 'passenger') {
            $query->where('passenger_id', $user->id);
        } elseif ($user->role === 'driver') {
            $query->whereHas('trip', fn ($trip) => $trip->where('user_id', $user->id));
        } else {
            abort(403);
        }

        $bookings = $query->latest('updated_at')->paginate(8);

        return view('ratings.pending', compact('bookings'));
    }

    public function store(StoreRatingRequest $request, Booking $booking): RedirectResponse
    {
        [$reviewee] = $this->ratingParticipants($request, $booking);
        abort_if($booking->ratings()->where('reviewer_id', $request->user()->id)->exists(), 409, 'You have already rated this booking.');
        Rating::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $reviewee->id,
            ...$request->validated(),
        ]);

        return redirect()->route('ratings.index')->with('success', 'Thank you! Your rating was submitted.');
    }

    public function received(Request $request, User $user): View
    {
        abort_unless($this->canViewReceivedRatings($request, $user), 403);
        $query = $user->ratingsReceived()->with('reviewer');
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($q) => $q->where('comment', 'like', "%{$search}%")
                ->orWhereHas('reviewer', fn ($reviewer) => $reviewer->where('name', 'like', "%{$search}%")));
        }
        if ($request->input('sort') === 'highest') $query->orderByDesc('score')->latest();
        elseif ($request->input('sort') === 'lowest') $query->orderBy('score')->latest();
        else $query->latest();
        $ratings = $query->paginate(8)->withQueryString();
        $average = round((float) $user->ratingsReceived()->avg('score'), 1);

        return view('ratings.received', compact('user', 'ratings', 'average'));
    }

    private function ratingParticipants(Request $request, Booking $booking): array
    {
        $booking->loadMissing(['trip.user', 'passenger']);
        abort_unless($booking->booking_status === 'Accepted' && $booking->trip->status === 'Completed', 422, 'Ratings are available after a completed trip.');
        $actor = $request->user();
        if ($actor->id === $booking->passenger_id) return [$booking->trip->user];
        if ($actor->id === $booking->trip->user_id) return [$booking->passenger];
        abort(403);
    }

    private function canViewReceivedRatings(Request $request, User $user): bool
    {
        if ($request->user()->is($user)) return true;
        return Booking::where('passenger_id', $request->user()->id)
            ->whereHas('trip', fn ($q) => $q->where('user_id', $user->id))->exists()
            || Booking::where('passenger_id', $user->id)
                ->whereHas('trip', fn ($q) => $q->where('user_id', $request->user()->id))->exists();
    }
}
