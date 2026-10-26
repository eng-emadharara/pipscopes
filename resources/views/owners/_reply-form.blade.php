{{-- Owner mode: reply box under a review. Errors use a bag per review so only this box reopens. --}}
@php
    $bag = $errors->getBag('reply'.$review->id);
    $hasReply = filled($review->provider_reply);
@endphp
<div class="mt-4 rounded-xl border border-dashed border-sky-300 bg-sky-50/40 p-3">
    <details @if (! $hasReply || $bag->any()) open @endif>
        <summary class="cursor-pointer list-none text-sm font-semibold text-sky-800 {{ $hasReply ? '' : 'hidden' }}">
            ✏️ {{ __('تعديل ردك') }}
        </summary>
        <form method="POST" action="{{ route('owner.reply', [$provider, $review]) }}" class="mt-2 space-y-2">
            @csrf
            <input type="hidden" name="page" value="{{ request('page') }}">
            <label for="reply-{{ $review->id }}" class="label">{{ $hasReply ? __('ردك') : __('رد على هذا التقييم كصاحب القناة') }}</label>
            <textarea id="reply-{{ $review->id }}" name="provider_reply" rows="3" dir="auto" minlength="5" maxlength="2000" required
                      class="input" placeholder="{{ __('اشكر المقيّم أو وضّح موقفك بأسلوب مهني. سيظهر ردك علناً.') }}">{{ old('provider_reply', $review->provider_reply) }}</textarea>
            @if ($bag->has('provider_reply'))
                <p class="error">{{ $bag->first('provider_reply') }}</p>
            @endif
            <button class="btn bg-sky-600 px-4 py-2 text-white hover:bg-sky-700">{{ $hasReply ? __('تحديث الرد') : __('نشر الرد') }}</button>
        </form>
    </details>

    @if ($hasReply)
        <form method="POST" action="{{ route('owner.reply.delete', [$provider, $review]) }}" class="mt-2"
              onsubmit="return confirm(@js(__('حذف ردك على هذا التقييم؟')))">
            @csrf @method('DELETE')
            <input type="hidden" name="page" value="{{ request('page') }}">
            <button class="text-xs text-red-600 hover:underline">{{ __('حذف الرد') }}</button>
        </form>
    @endif
</div>
