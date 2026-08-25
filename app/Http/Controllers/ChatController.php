<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $bookings = Booking::query()
            ->with([
                'passenger',
                'trip.user',
                'trip.vehicle',
                'latestMessage',
            ])
            ->withCount(['messages as unread_messages_count' => fn ($messages) => $messages
                ->where('receiver_id', $user->id)
                ->whereNull('read_at')])
            ->withMax('messages', 'created_at')
            ->when(
                $user->role === 'driver',
                fn ($query) => $query->whereHas('trip', fn ($trip) => $trip->where('user_id', $user->id)),
                fn ($query) => $query->where('passenger_id', $user->id)
            )
            ->orderByDesc('messages_max_created_at')
            ->latest('created_at')
            ->paginate(10);

        return view('bookings.messages', compact('bookings'));
    }

    public function show(Request $request, Booking $booking): View
    {
        $booking->loadMissing(['passenger', 'trip.user', 'trip.vehicle']);
        $this->authorizeParticipant($request, $booking);

        $booking->messages()
            ->where('receiver_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $booking->messages()
            ->with('sender:id,name')
            ->oldest()
            ->get()
            ->map(fn ($message) => $this->messagePayload($message));

        return view('bookings.chat', [
            'booking' => $booking,
            'messages' => $messages,
            'canSend' => $this->canSendMessage($booking),
            'otherUser' => $this->otherUserFor($request, $booking),
        ]);
    }

    public function store(Request $request, Booking $booking): JsonResponse|RedirectResponse
    {
        $booking->loadMissing(['passenger', 'trip.user']);
        $this->authorizeParticipant($request, $booking);

        abort_unless($this->canSendMessage($booking), 422, 'This chat is read-only.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);
        $text = trim($validated['message']);

        abort_if($text === '', 422, 'Message is required.');

        $message = $booking->messages()->create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $this->otherUserFor($request, $booking)->id,
            'message' => $text,
        ]);

        MessageSent::dispatch($message);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messagePayload($message->load('sender:id,name')),
            ], 201);
        }

        return back();
    }

    private function authorizeParticipant(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->id === $booking->passenger_id || $user->id === $booking->trip->user_id,
            403
        );
    }

    private function canSendMessage(Booking $booking): bool
    {
        return in_array($booking->booking_status, ['Pending', 'Accepted'], true)
            && ! in_array($booking->trip->status, ['Completed', 'Cancelled'], true);
    }

    private function otherUserFor(Request $request, Booking $booking)
    {
        return $request->user()->id === $booking->passenger_id
            ? $booking->trip->user
            : $booking->passenger;
    }

    private function messagePayload($message): array
    {
        return [
            'id' => $message->id,
            'booking_id' => $message->booking_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'message' => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_label' => $message->created_at?->format('g:i A'),
        ];
    }
}
