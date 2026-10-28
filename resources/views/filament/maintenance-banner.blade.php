{{-- Inline styles: the panel's stylesheet only contains Filament's own classes --}}
<div style="background: linear-gradient(90deg, #b91c1c, #dc2626); color: #fff; font-size: 0.875rem; font-weight: 500;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.5rem 1rem; padding: 0.5rem 1rem; text-align: center;">
        <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: #fff; box-shadow: 0 0 0 3px rgba(255,255,255,.3);"></span>
            وضع الصيانة مفعّل — الزوار يرون صفحة الصيانة الآن.
        </span>
        <a href="{{ \App\Filament\Pages\MaintenanceSettings::getUrl() }}" style="text-decoration: underline; font-weight: 700;">إدارة الصيانة</a>
    </div>
</div>
