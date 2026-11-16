<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::button type="submit">حفظ</x-filament::button>
            <a class="text-sm text-primary-600 underline dark:text-primary-400" href="{{ route('site.kitchen') }}" target="_blank">افتح صفحة المطبخ في الموقع</a>
        </div>
    </form>
</x-filament-panels::page>
