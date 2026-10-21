{{-- One-time messages after a redirect: status (done) or error (could not). --}}
@foreach (['status' => 'ns-alert--ok', 'error' => 'ns-alert--error'] as $key => $class)
    @if (session($key))
        <p class="ns-alert {{ $class }}" role="{{ $key === 'error' ? 'alert' : 'status' }}">{{ session($key) }}</p>
    @endif
@endforeach
