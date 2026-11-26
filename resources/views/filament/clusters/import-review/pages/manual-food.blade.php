<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::button type="submit" icon="heroicon-o-check">احفظ في القاعدة</x-filament::button>
            <x-filament::link :href="\App\Filament\Clusters\ImportReview\Pages\AddFoods::getUrl()">رجوع لإضافة أغذية</x-filament::link>
        </div>
    </form>
</x-filament-panels::page>
