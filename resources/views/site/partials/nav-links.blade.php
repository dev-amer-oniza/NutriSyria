{{-- Params: $items = [['label', 'route'], ...] from config('site'). --}}
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       @if (request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
@endforeach
