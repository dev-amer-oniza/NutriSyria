{{-- Bot trap: hidden from people and screen readers, filled only by bots (AuthFormRequest::HONEYPOT). --}}
<div class="ns-hp" aria-hidden="true">
    <label for="f-website">اترك هذا الحقل فارغاً</label>
    <input id="f-website" name="{{ \App\Http\Requests\Auth\AuthFormRequest::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off">
</div>
