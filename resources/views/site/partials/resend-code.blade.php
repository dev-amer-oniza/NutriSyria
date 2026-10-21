{{-- "Send the code again", disabled with a countdown while the last code is fresh. Params: $action, $seconds. --}}
<form class="ns-resend" method="post" action="{{ $action }}" x-data="{ wait: {{ (int) $seconds }} }"
      x-init="const t = setInterval(() => { if (wait > 0) wait--; else clearInterval(t) }, 1000)">
    @csrf
    <span>لم يصلك الرمز؟</span>
    <button class="ns-link ns-resend__btn" type="submit" :disabled="wait > 0" @disabled($seconds > 0)>
        أرسله مجدداً<span x-show="wait > 0" x-cloak> بعد <span class="ns-num" x-text="wait">{{ $seconds }}</span> ثانية</span>
    </button>
</form>
