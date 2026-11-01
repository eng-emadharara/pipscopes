{{--
    "You're signed in", over whatever page they land on. Kept in the session until a page shows it, not flashed:
    the page waiting for the Telegram app makes background requests, and any of them would use up a flash message.
--}}
@if ($notice = session()->pull(\App\Support\SocialLogin::NOTICE_KEY))
    <div class="fixed inset-x-4 top-4 z-[60] mx-auto flex max-w-sm items-start gap-3 rounded-2xl bg-white p-4 text-ink shadow-2xl ring-1 ring-black/10" role="status" data-toast>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500 text-white"><x-ui-icon name="check" class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-bold">{{ $notice['title'] }}</p>
            @if ($notice['name'] ?? null)
                <p class="mt-0.5 truncate text-sm text-gray-600">{{ __('أهلاً بك، :name', ['name' => $notice['name']]) }}</p>
            @endif
        </div>
        <button type="button" class="shrink-0 px-1 text-xl leading-none text-gray-400 hover:text-gray-700" data-toast-close aria-label="{{ __('إغلاق') }}">&times;</button>
    </div>
@endif
