{{-- One-tap choice of how long the member used the service; used by the review form and the thank-you page --}}
<fieldset>
    <legend class="label">{{ __('مدة تجربتك مع الخدمة') }}</legend>
    <div class="flex flex-wrap gap-2">
        @foreach (__('trust.experience_durations') as $key => $label)
            <label class="chip chip-pro relative">
                <input type="radio" name="experience_duration" value="{{ $key }}" class="sr-only" @checked($selected === $key)>
                {{ $label }}
            </label>
        @endforeach
    </div>
</fieldset>
