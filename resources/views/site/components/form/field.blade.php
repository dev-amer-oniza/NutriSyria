{{--
    Text input with its label and error. Params: $name, $label, $type (text), $autocomplete,
    $inputmode, $dir (for emails and numbers: ltr), $placeholder, $hint, $value (old() wins),
    $required (true), $autofocus (false).
--}}
@php($id = 'f-'.$name)
<div class="ns-field">
    <label class="ns-field__label" for="{{ $id }}">{{ $label }}</label>
    <input class="ns-input" id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
           value="{{ old($name, $value ?? '') }}"
           @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset
           @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
           @isset($dir) dir="{{ $dir }}" @endisset
           @isset($placeholder) placeholder="{{ $placeholder }}" @endisset
           @if ($required ?? true) required @endif
           @if ($autofocus ?? false) autofocus @endif
           @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
    @isset($hint)<p class="ns-field__hint">{{ $hint }}</p>@endisset
    @error($name)<p class="ns-field__error" id="{{ $id }}-error">{{ $message }}</p>@enderror
</div>
