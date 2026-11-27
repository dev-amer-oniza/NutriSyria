@php($back = \App\Filament\Support\BackLink::for(\Livewire\Livewire::current()))
@if ($back)
    <div class="ns-back mb-3">
        <x-filament::button tag="a" :href="$back['url']" icon="heroicon-m-arrow-right" color="primary" size="sm">
            {{ $back['label'] }}
        </x-filament::button>
    </div>
@endif
