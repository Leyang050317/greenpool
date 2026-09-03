@props(['booking', 'otherUser'])

@php
    $messages = $booking->messages()->with('sender:id,name')->latest()->take(6)->get()->reverse()->values()->map(fn ($message) => [
        'id' => $message->id,
        'sender_id' => $message->sender_id,
        'sender_name' => $message->sender->name,
        'message' => $message->message,
        'created_at_label' => $message->created_at?->format('g:i A'),
    ]);
@endphp

<section class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm sm:p-5" x-data="bookingChat({ bookingId: {{ Js::from($booking->id) }}, currentUserId: {{ Js::from(Auth::id()) }}, messages: {{ Js::from($messages) }}, canSend: true, endpoint: {{ Js::from(route('bookings.chat.store', $booking)) }} })">
    <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-green-50 text-green-600"><x-icons.lucide name="message-square" class="h-4 w-4" /></span><div><h3 class="text-sm font-semibold text-gray-900">Trip chat with {{ $otherUser->name }}</h3><p class="mt-0.5 text-xs text-gray-500">Message each other without leaving this active trip.</p></div></div><a href="{{ route('bookings.chat.show', $booking) }}" class="text-xs font-semibold text-green-700 hover:text-green-800">Full chat</a></div>
    <div x-ref="messages" class="mt-4 max-h-52 space-y-2 overflow-y-auto rounded-xl bg-slate-50 p-3">
        <template x-if="messages.length === 0"><p class="py-3 text-center text-xs text-slate-500">No messages yet. Use this chat for pickup or trip questions.</p></template>
        <template x-for="chatMessage in messages" :key="chatMessage.id"><div class="flex" :class="isMine(chatMessage) ? 'justify-end' : 'justify-start'"><div class="max-w-[82%] rounded-xl px-3 py-2 text-sm" :class="isMine(chatMessage) ? 'bg-green-600 text-white' : 'bg-white text-slate-800 shadow-sm'"><p class="whitespace-pre-wrap break-words" x-text="chatMessage.message"></p><p class="mt-1 text-[10px]" :class="isMine(chatMessage) ? 'text-green-100' : 'text-slate-400'" x-text="chatMessage.created_at_label"></p></div></div></template>
    </div>
    <form class="mt-3 flex gap-2" @submit.prevent="sendMessage"><input x-model="message" maxlength="1000" placeholder="Message {{ $otherUser->name }}..." class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-green-600 focus:ring-green-600"><button type="submit" :disabled="sending || !message.trim()" class="inline-flex items-center justify-center rounded-xl bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"><x-icons.lucide name="send" class="h-4 w-4" /></button></form>
</section>
