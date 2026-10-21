{{-- Choice of account as three cards. Params: $types (AccountType cases); binds Alpine `type`. --}}
<fieldset class="ns-field ns-types">
    <legend class="ns-field__label">أنشئ حسابك بصفتك</legend>
    <div class="ns-types__grid">
        @foreach ($types as $type)
            <label class="ns-type">
                <input class="ns-type__radio" type="radio" name="account_type" value="{{ $type->value }}" x-model="type"
                       @checked(old('account_type', 'member') === $type->value)>
                <span class="ns-type__name">{{ $type->label() }}</span>
                <span class="ns-type__hint">{{ $type->hint() }}</span>
            </label>
        @endforeach
    </div>
    @error('account_type')<p class="ns-field__error">{{ $message }}</p>@enderror
</fieldset>
