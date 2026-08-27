<div x-data="faqBot({ featuredEndpoint: @js(route('faq-bot.featured')), answerEndpoint: @js(route('faq-bot.answer')) })" class="fixed bottom-24 right-5 z-[90] [font-family:Inter,sans-serif] sm:bottom-24">
    <button
        type="button"
        x-show="!open"
        x-transition
        @click="open = true; if (!featured.length) loadFeatured(); $nextTick(() => $refs.question.focus())"
        @open-faq-bot.window="openBot($event)"
        class="flex h-14 w-14 items-center justify-center rounded-full bg-[#16803c] text-white shadow-lg shadow-green-900/20 transition hover:bg-[#126b32] focus:outline-none focus:ring-4 focus:ring-green-200"
        aria-label="Open GreenPool help bot"
        title="GreenPool Help"
    >
        <x-icons.lucide name="message-square" class="h-6 w-6" />
        <span class="sr-only">GreenPool Help</span>
    </button>

    <section
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-3 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-3 opacity-0"
        class="flex h-[min(38rem,calc(100vh-2.5rem))] w-[calc(100vw-2.5rem)] max-w-[25rem] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-label="GreenPool FAQ help bot"
    >
        <header class="flex items-start justify-between bg-[#16803c] px-5 py-4 text-white">
            <div class="flex gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15"><x-icons.lucide name="message-square" class="h-5 w-5" /></span>
                <div><h2 class="font-bold">GreenPool Help Bot</h2><p class="mt-0.5 text-xs text-green-100">FAQ support · No personal data is shared</p></div>
            </div>
            <button type="button" @click="open = false" class="rounded-lg p-1 text-green-100 hover:bg-white/10 hover:text-white" aria-label="Close help bot"><x-icons.lucide name="x" class="h-5 w-5" /></button>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4" x-ref="conversation" aria-live="polite">
            <div class="max-w-[90%] rounded-2xl rounded-tl-sm bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">Hi! I can help you use GreenPool. Ask about trips, bookings, payments, ratings, messages, notifications, or tourist attractions.</div>

            <template x-for="message in messages" :key="message.id">
                <div :class="message.role === 'user' ? 'ml-auto bg-[#16803c] text-white' : 'mr-auto bg-white text-slate-700 shadow-sm'" class="max-w-[90%] whitespace-pre-line rounded-2xl px-4 py-3 text-sm leading-6" x-text="message.text"></div>
            </template>

            <template x-if="loading"><div class="mr-auto w-fit rounded-2xl bg-white px-4 py-3 text-sm text-slate-500 shadow-sm">Finding the best answer…</div></template>

            <template x-if="featured.length && messages.length === 0">
                <div class="pt-1">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Common questions</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="faq in featured" :key="faq.id"><button type="button" @click="ask(faq.question)" class="rounded-full border border-green-200 bg-white px-3 py-1.5 text-left text-xs font-medium text-green-800 hover:bg-green-50" x-text="faq.question"></button></template>
                    </div>
                </div>
            </template>

            <template x-if="suggestions.length">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">You could try</p>
                    <div class="flex flex-wrap gap-2"><template x-for="faq in suggestions" :key="faq.id"><button type="button" @click="ask(faq.question)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-left text-xs font-medium text-slate-600 hover:bg-slate-100" x-text="faq.question"></button></template></div>
                </div>
            </template>
        </div>

        <form @submit.prevent="ask(question)" class="border-t border-slate-200 bg-white p-3">
            <label class="sr-only" for="faq-bot-question">Ask a question</label>
            <div class="flex gap-2">
                <input id="faq-bot-question" x-ref="question" x-model="question" :disabled="loading" maxlength="300" placeholder="Ask a GreenPool question…" class="min-w-0 flex-1 rounded-xl border-slate-200 px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-green-600 focus:ring-green-600 disabled:bg-slate-100">
                <button type="submit" :disabled="loading || !question.trim()" class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#16803c] text-white hover:bg-[#126b32] disabled:cursor-not-allowed disabled:bg-slate-300" aria-label="Send question"><x-icons.lucide name="send" class="h-4 w-4" /></button>
            </div>
            <p class="mt-2 text-center text-[11px] text-slate-400">Answers come from GreenPool's approved FAQ content.</p>
        </form>
    </section>
</div>
