{{--
    One-time messages after a redirect (the owner's request): a short card that slides down
    at the top of the page, stays a few seconds, and goes by itself. Done = TIMES['status'],
    a problem stays a little longer to be read. Pointing at it or focusing it pauses it;
    the × closes it now. Without JavaScript it simply stays.
--}}
@php($messages = array_filter(['status' => session('status'), 'error' => session('error')]))

@if ($messages)
    <div class="ns-toasts">
        @foreach ($messages as $kind => $message)
            @php($timeout = ['status' => 4000, 'error' => 7000][$kind])
            <div class="ns-alert ns-alert--{{ $kind === 'status' ? 'ok' : 'error' }}"
                 role="{{ $kind === 'error' ? 'alert' : 'status' }}"
                 style="--flash-ms: {{ $timeout }}ms"
                 x-data="flash({{ $timeout }})" x-show="open"
                 x-transition:enter="ns-toast-in" x-transition:enter-start="is-off"
                 x-transition:leave="ns-toast-in" x-transition:leave-end="is-off"
                 @mouseenter="pause" @mouseleave="resume" @focusin="pause" @focusout="resume">
                <span class="ns-alert__mark" aria-hidden="true">{{ $kind === 'status' ? '✓' : '!' }}</span>
                <p class="ns-alert__text">{{ $message }}</p>
                <button class="ns-alert__close" type="button" @click="close" aria-label="إغلاق الرسالة">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                <span class="ns-alert__timer" :class="{ 'is-paused': paused }" aria-hidden="true"></span>
            </div>
        @endforeach
    </div>
@endif
