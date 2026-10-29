{{-- Tick chips for what the member liked ($type = 'pros') or disliked ('cons'); used by the review form and the thank-you page --}}
<div class="reason-group" data-for="{{ $type }}">
    <span class="label">{{ $type === 'pros' ? __('ما الذي أعجبك؟') : __('ما الذي لم يعجبك؟') }}</span>
    <div class="flex flex-wrap gap-2">
        @foreach (__("trust.$type") as $key => $label)
            <label class="chip {{ $type === 'pros' ? 'chip-pro' : 'chip-con' }} relative">
                <input type="checkbox" name="{{ $type }}[]" value="{{ $key }}" class="sr-only" @checked(in_array($key, $selected))>
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>
