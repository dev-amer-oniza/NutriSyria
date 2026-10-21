{{-- The 6-digit emailed code. The phone offers it from the message (one-time-code). --}}
<div class="ns-field">
    <label class="ns-field__label" for="f-code">الرمز المرسل إلى بريدك</label>
    <input class="ns-input ns-input--code" id="f-code" name="code" type="text" inputmode="numeric"
           autocomplete="one-time-code" maxlength="6" pattern="[0-9٠-٩]*" dir="ltr" required autofocus
           placeholder="••••••" @error('code') aria-invalid="true" aria-describedby="f-code-error" @enderror>
    @error('code')<p class="ns-field__error" id="f-code-error">{{ $message }}</p>@enderror
</div>
