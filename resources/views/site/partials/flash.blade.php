{{--
    One-time messages after a redirect. A status (something done) closes by itself after
    STATUS_MS, pausing while pointed at or focused; an error stays until closed, since it
    asks the visitor to act. Both have a close button. Param $toast: float over the page.
--}}
@php($messages = array_filter(['status' => session('status'), 'error' => session('error')]))

@if ($messages)
    <div @class(['ns-toasts' => $toast ?? false])>
        @foreach ($messages as $kind => $message)
            @php($timeout = $kind === 'status' ? 6000 : 0)
            <div class="ns-alert ns-alert--{{ $kind === 'status' ? 'ok' : 'error' }}"
                 role="{{ $kind === 'error' ? 'alert' : 'status' }}"
                 style="--flash-ms: {{ $timeout }}ms"
                 x-data="flash({{ $timeout }})" x-show="open" x-transition.opacity.duration.300ms
                 @mouseenter="pause" @mouseleave="resume" @focusin="pause" @focusout="resume">
                <p class="ns-alert__text">{{ $message }}</p>
                <button class="ns-alert__close" type="button" @click="close" aria-label="إغلاق الرسالة">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                @if ($timeout)<span class="ns-alert__timer" :class="{ 'is-paused': paused }" aria-hidden="true"></span>@endif
            </div>
        @endforeach
    </div>
@endif
