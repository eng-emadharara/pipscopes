{{-- The instrument, and its price when a pip's dollar value depends on it (resources/js/calculators.js) --}}
<div>
    <label class="label" for="symbol">{{ __('الأداة') }}</label>
    <select id="symbol" name="symbol" class="input" dir="ltr">
        @foreach (['XAUUSD' => __('الذهب'), 'XAGUSD' => __('الفضة'), 'EURUSD' => null, 'GBPUSD' => null, 'AUDUSD' => null, 'NZDUSD' => null, 'USDJPY' => null, 'USDCHF' => null, 'USDCAD' => null, 'BTCUSD' => __('بيتكوين')] as $symbol => $name)
            <option value="{{ $symbol }}">{{ $symbol }}{{ $name ? ' — '.$name : '' }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">{{ __('حجم النقطة:') }} <span dir="ltr" data-pip-size>0.1</span></p>
</div>
@unless ($noPrice ?? false)
    <div data-price-field hidden>
        <label class="label" for="price">{{ __('سعر الزوج الحالي') }}</label>
        <input id="price" name="price" type="number" step="any" min="0" class="input" dir="ltr" inputmode="decimal">
        <p class="mt-1 text-xs text-gray-500">{{ __('لهذا الزوج تتغير قيمة النقطة بالدولار حسب سعره.') }}</p>
    </div>
@endunless
