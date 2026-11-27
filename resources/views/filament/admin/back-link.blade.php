@php($back = \App\Filament\Support\BackLink::for($scopes ?? []))
@if ($back)
    <x-filament::link :href="$back['url']" icon="heroicon-m-arrow-right" color="gray" size="sm" class="ns-back-link mb-1">
        {{ $back['label'] }}
    </x-filament::link>
@endif
