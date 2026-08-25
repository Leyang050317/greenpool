<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
                <p class="mt-1 text-sm text-gray-500">Conversations for your booking requests.</p>
            </div>

            @if($bookings->isEmpty())
                <div class="rounded-xl border border-gray-100 bg-white px-6 py-16 text-center">
                    <p class="text-base font-semibold text-gray-700">No messages yet.</p>
                    <p class="mt-1 text-sm text-gray-500">Your booking conversations will appear here.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                    <div class="divide-y divide-gray-50">
                        @foreach($bookings as $booking)
                            @php
                                $otherUser = Auth::id() === $booking->passenger_id ? $booking->trip->user : $booking->passenger;
                                $lastMessage = $booking->latestMessage;
                            @endphp
                            <a
                                href="{{ route('bookings.chat.show', $booking) }}"
                                class="flex gap-4 px-5 py-4 transition hover:bg-green-50/60"
                                data-chat-booking-id="{{ $booking->id }}"
                            >
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-bold text-white">
                                    {{ mb_strtoupper(mb_substr($otherUser->name, 0, 1)) }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start justify-between gap-3">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-gray-900">{{ $otherUser->name }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $booking->trip->departure_location }} to {{ $booking->trip->destination }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs text-gray-400">{{ $lastMessage?->created_at?->diffForHumans() }}</span>
                                    </span>

                                    <span class="mt-2 flex items-center justify-between gap-3">
                                        <span class="min-w-0 truncate text-sm {{ $booking->unread_messages_count > 0 ? 'font-semibold text-gray-900' : 'text-gray-500' }}">
                                            {{ $lastMessage?->message ?? 'No messages yet.' }}
                                        </span>
                                        @if($booking->unread_messages_count > 0)
                                            <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">
                                                {{ min($booking->unread_messages_count, 99) }}
                                            </span>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
