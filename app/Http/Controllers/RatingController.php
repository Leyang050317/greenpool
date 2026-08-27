<?php

namespace App\Http\Controllers;

use App\Events\RatingReceived;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Requests\UpdateRatingRequest;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use App\Notifications\RatingReceivedNotification;
use Illuminate\Database\Eloquent\Builder;
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

        $query->orderBy('created_at', $request->input('sort') === 'oldest' ? 'asc' : 'desc');
        $ratings = $query->paginate(8)->withQueryString();
        $targetRole = $request->user()->role === 'passenger' ? 'driver' : 'passenger';
        $stats = [
            'total' => $request->user()->ratingsGiven()->count(),
            'rated' => $request->user()->ratingsGiven()
                ->whereHas('reviewee', fn ($q) => $q->where('role', $targetRole))
                ->count(),
        ];

        return view('ratings.index', compact('ratings', 'stats', 'targetRole'));
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

    public function reviews(Request $request): View
    {
        $user = $request->user();
        $targetRole = $user->role === 'passenger' ? 'driver' : 'passenger';
        $query = Rating::query()->with(['reviewer', 'reviewee'])
            ->whereHas('reviewee', function ($reviewee) use ($user, $targetRole) {
                $reviewee->where('role', $targetRole);
                if ($user->role === 'passenger') {
                    $reviewee->whereHas('trips.bookings', fn ($booking) => $booking
                        ->where('passenger_id', $user->id)->where('booking_status', 'Accepted'));
                } else {
                    $reviewee->whereHas('bookings', fn ($booking) => $booking
                        ->where('booking_status', 'Accepted')
                        ->whereHas('trip', fn ($trip) => $trip->where('user_id', $user->id)));
                }
            });

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($rating) => $rating->where('comment', 'like', "%{$search}%")
                ->orWhereHas('reviewee', fn ($reviewee) => $reviewee->where('name', 'like', "%{$search}%"))
                ->orWhereHas('reviewer', fn ($reviewer) => $reviewer->where('name', 'like', "%{$search}%")));
        }

        $breakdown = (clone $query)
            ->selectRaw('score, COUNT(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');
        $reviewCount = (int) $breakdown->sum();
        $average = $reviewCount > 0
            ? round((float) $breakdown->map(fn ($total, $score) => $total * $score)->sum() / $reviewCount, 1)
            : 0.0;

        if ($request->input('sort') === 'highest') {
            $query->orderByDesc('score')->latest();
        } elseif ($request->input('sort') === 'lowest') {
            $query->orderBy('score')->latest();
        } else {
            $query->latest();
        }

        $ratings = $query->paginate(8)->withQueryString();

        return view('ratings.reviews', compact('ratings', 'targetRole', 'breakdown', 'reviewCount', 'average'));
    }

    public function create(Request $request, Booking $booking): View|RedirectResponse
    {
        [$reviewee] = $this->ratingParticipants($request, $booking);
        $existingRating = $booking->ratings()->where('reviewer_id', $request->user()->id)->first();
        if ($existingRating) {
            return redirect()->route('ratings.submitted', $existingRating)
                ->with('success', 'You already rated this booking. Here is your submitted rating.');
        }

        return view('ratings.create', compact('booking', 'reviewee'));
    }

    public function pending(Request $request): View
    {
        $user = $request->user();
        $bookings = $this->pendingBookingsQuery($user)
            ->latest('updated_at')
            ->paginate(8);

        return view('ratings.pending', compact('bookings'));
    }

    public function store(StoreRatingRequest $request, Booking $booking): RedirectResponse
    {
        [$reviewee] = $this->ratingParticipants($request, $booking);
        $existingRating = $booking->ratings()->where('reviewer_id', $request->user()->id)->first();
        if ($existingRating) {
            return redirect()->route('ratings.submitted', $existingRating)
                ->with('success', 'You already rated this booking. Your previous rating was kept.');
        }
        $rating = Rating::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $reviewee->id,
            ...$request->validated(),
        ]);
        $reviewee->notify(new RatingReceivedNotification($rating->load('reviewer')));
        RatingReceived::dispatch($rating->load('reviewee'));

        return redirect()->route('ratings.submitted', $rating);
    }

    public function submitted(Request $request, Rating $rating): View
    {
        $this->ensureRatingOwnership($request, $rating);
        $rating->loadMissing(['reviewee', 'booking.trip']);
        $nextBooking = $this->pendingBookingsQuery($request->user())
            ->where('trip_id', $rating->booking->trip_id)
            ->oldest('id')
            ->first();

        return view('ratings.submitted', compact('rating', 'nextBooking'));
    }

    public function edit(Request $request, Rating $rating): View
    {
        $this->ensureRatingOwnership($request, $rating);
        abort_unless($rating->isEditable(), 423, 'This rating is locked because the 7-day editing period has ended.');
        $rating->loadMissing(['booking.trip', 'reviewee']);

        return view('ratings.edit', compact('rating'));
    }

    public function update(UpdateRatingRequest $request, Rating $rating): RedirectResponse
    {
        abort_unless($rating->isEditable(), 423, 'This rating is locked because the 7-day editing period has ended.');
        $rating->update($request->validated());

        return redirect()->route('ratings.history')->with('success', 'Your rating was updated successfully.');
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
        if ($request->input('sort') === 'highest') {
            $query->orderByDesc('score')->latest();
        } elseif ($request->input('sort') === 'lowest') {
            $query->orderBy('score')->latest();
        } else {
            $query->latest();
        }
        $ratings = $query->paginate(8)->withQueryString();
        $average = round((float) $user->ratingsReceived()->avg('score'), 1);

        return view('ratings.received', compact('user', 'ratings', 'average'));
    }

    private function ratingParticipants(Request $request, Booking $booking): array
    {
        $booking->loadMissing(['trip.user', 'passenger', 'payment']);
        abort_unless($booking->booking_status === 'Accepted' && $booking->trip->status === 'Completed', 422, 'Ratings are available after a completed trip.');
        abort_if(
            $booking->trip->completed_at?->addDays(7)->isPast(),
            410,
            'The 7-day rating period for this trip has expired.'
        );
        $actor = $request->user();
        if ($actor->id === $booking->passenger_id) {
            abort_if($booking->payment && ! $booking->payment->isPaid(), 402, 'Please complete payment before rating your driver.');

            return [$booking->trip->user];
        }
        if ($actor->id === $booking->trip->user_id) {
            return [$booking->passenger];
        }
        abort(403);
    }

    private function pendingBookingsQuery(User $user): Builder
    {
        $query = Booking::query()
            ->with(['passenger', 'trip.user'])
            ->where('booking_status', 'Accepted')
            ->whereHas('trip', fn ($trip) => $trip->where('status', 'Completed'))
            ->whereDoesntHave('ratings', fn ($rating) => $rating->where('reviewer_id', $user->id));

        if ($user->role === 'passenger') {
            return $query
                ->where('passenger_id', $user->id)
                ->where(fn ($booking) => $booking
                    ->whereDoesntHave('payment')
                    ->orWhereHas('payment', fn ($payment) => $payment->where('payment_status', 'Paid')));
        }

        if ($user->role === 'driver') {
            return $query->whereHas('trip', fn ($trip) => $trip->where('user_id', $user->id));
        }

        abort(403);
    }

    private function canViewReceivedRatings(Request $request, User $user): bool
    {
        if ($request->user()->is($user)) {
            return true;
        }

        return Booking::where('passenger_id', $request->user()->id)
            ->whereHas('trip', fn ($q) => $q->where('user_id', $user->id))->exists()
            || Booking::where('passenger_id', $user->id)
                ->whereHas('trip', fn ($q) => $q->where('user_id', $request->user()->id))->exists();
    }

    private function ensureRatingOwnership(Request $request, Rating $rating): void
    {
        abort_unless($rating->reviewer_id === $request->user()->id, 403);
    }
}
