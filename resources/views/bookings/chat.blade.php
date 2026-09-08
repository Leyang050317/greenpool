<x-app-layout>
    <div
        class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8"
        x-data="bookingChat({
            bookingId: {{ Js::from($booking->id) }},
            currentUserId: {{ Js::from(Auth::id()) }},
            messages: {{ Js::from($messages) }},
            canSend: {{ Js::from($canSend) }},
            endpoint: {{ Js::from(route('bookings.chat.store', $booking)) }},
            loginUrl: {{ Js::from(route('login')) }}
        })"
    >
        <div class="mx-auto flex max-w-4xl flex-col">
            <div class="mb-6 flex items-center justify-between gap-4">
                <a
                    href="{{ route('messages.index') }}"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-800"
                >
                    <x-icons.lucide name="arrow-left" class="h-4 w-4" />
                    Back
                </a>
                <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                    {{ $booking->booking_status }}
                </span>
            </div>

            <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <header class="border-b border-gray-100 px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Chat with {{ $otherUser->name }}</h1>
                            <p class="mt-1 text-sm text-gray-500">{{ $booking->trip->departure_location }} to {{ $booking->trip->destination }}</p>
                        </div>
                        <div class="hidden text-right text-xs text-gray-500 sm:block">
                            <p>{{ $booking->trip->departure_at->format('d M Y') }}</p>
                            <p>{{ $booking->trip->departure_at->format('g:i A') }}</p>
                        </div>
                    </div>
                </header>

                <div x-ref="messages" class="h-[55vh] min-h-[22rem] space-y-4 overflow-y-auto bg-slate-50 px-5 py-5">
                    <template x-if="messages.length === 0">
                        <div class="flex h-full items-center justify-center text-center text-sm text-gray-500">
                            No messages yet.
                        </div>
                    </template>

                    <template x-for="chatMessage in messages" :key="chatMessage.id">
                        <div class="flex" :class="isMine(chatMessage) ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[78%] rounded-2xl px-4 py-3 shadow-sm" :class="isMine(chatMessage) ? 'bg-[#2E7D32] text-white' : 'border border-gray-100 bg-white text-gray-900'">
                                <p class="text-sm leading-6 whitespace-pre-wrap break-words [overflow-wrap:anywhere]" x-text="chatMessage.message"></p>
                                <p class="mt-1 text-[11px]" :class="isMine(chatMessage) ? 'text-green-100' : 'text-gray-400'" x-text="chatMessage.created_at_label"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <footer class="border-t border-gray-100 bg-white p-4">
                    @if($canSend)
                        <form class="flex gap-3" @submit.prevent="sendMessage">
                            <input
                                type="text"
                                x-model="message"
                                :readonly="sessionExpired"
                                maxlength="1000"
                                placeholder="Type a message..."
                                class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]"
                            >
                            <button
                                type="submit"
                                :disabled="sending || sessionExpired || !message.trim()"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <x-icons.lucide name="send" class="h-4 w-4" />
                                Send
                            </button>
                        </form>
                        <div x-cloak x-show="sendError" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
                            <span x-text="sendError"></span>
                            <a x-show="sessionExpired" :href="loginUrl" class="ml-1 font-semibold underline">Sign in</a>
                        </div>
                    @else
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                            This chat is read-only because the booking or trip is no longer active.
                        </div>
                    @endif
                </footer>
            </section>
        </div>
    </div>
</x-app-layout>
