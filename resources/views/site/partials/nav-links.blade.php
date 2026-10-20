{{-- Params: $items = [['label', 'route'], ...] from config('site'). --}}
@foreach ($items as $item)
    <a href="{{ \App\Support\Site\SiteLink::url($item['route']) }}"
       @if (\App\Support\Site\SiteLink::current($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
@endforeach
