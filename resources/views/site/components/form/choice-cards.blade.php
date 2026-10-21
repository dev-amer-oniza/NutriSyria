{{--
    A set of choice cards (radio: one; checkbox: several). Params:
    $name, $legend, $options = [value => ['label' => ..., 'hint' => ...?]], $selected (value or list),
    $multiple (false), $cols (3), $model (Alpine x-model, optional), $hint (under the legend, optional),
    $showErrors (true; false when another set of cards shares the same name).
--}}
@php
    $multiple ??= false;
    $selectedValues = (array) old($name, $selected ?? ($multiple ? [] : null));
@endphp
<fieldset class="ns-field ns-types">
    <legend class="ns-field__label">{{ $legend }}</legend>
    @isset($hint)<p class="ns-field__hint">{{ $hint }}</p>@endisset
    <div class="ns-types__grid" style="--cols: {{ $cols ?? 3 }}">
        @foreach ($options as $value => $option)
            <label class="ns-type">
                <input class="ns-type__radio" type="{{ $multiple ? 'checkbox' : 'radio' }}"
                       name="{{ $multiple ? $name.'[]' : $name }}" value="{{ $value }}"
                       @isset($model) x-model="{{ $model }}" @endisset
                       @checked(in_array((string) $value, array_map('strval', $selectedValues), true))>
                <span class="ns-type__name">{{ $option['label'] }}</span>
                @isset($option['hint'])<span class="ns-type__hint">{{ $option['hint'] }}</span>@endisset
            </label>
        @endforeach
    </div>
    @if ($showErrors ?? true)
        @error($name)<p class="ns-field__error">{{ $message }}</p>@enderror
        @if ($multiple)
            @error($name.'.*')<p class="ns-field__error">{{ $message }}</p>@enderror
        @endif
    @endif
</fieldset>
