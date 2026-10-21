{{-- Password with a show/hide button. Params: $label, $autocomplete (current-password | new-password), $hint. --}}
<div class="ns-field" x-data="{ shown: false }">
    <label class="ns-field__label" for="f-password">{{ $label }}</label>
    <div class="ns-password">
        <input class="ns-input" id="f-password" name="password" :type="shown ? 'text' : 'password'" type="password"
               autocomplete="{{ $autocomplete }}" dir="ltr" required maxlength="72"
               @error('password') aria-invalid="true" aria-describedby="f-password-error" @enderror>
        <button class="ns-password__toggle" type="button" @click="shown = ! shown"
                :aria-pressed="shown" aria-pressed="false" aria-controls="f-password"
                x-text="shown ? 'إخفاء' : 'إظهار'">إظهار</button>
    </div>
    @isset($hint)<p class="ns-field__hint">{{ $hint }}</p>@endisset
    @error('password')<p class="ns-field__error" id="f-password-error">{{ $message }}</p>@enderror
</div>
