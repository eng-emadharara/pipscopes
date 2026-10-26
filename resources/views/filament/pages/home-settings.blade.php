<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button type="submit">حفظ</x-filament::button>
            <x-filament::link :href="route('home')" target="_blank">عرض الصفحة الرئيسية</x-filament::link>
        </div>
    </form>
</x-filament-panels::page>
