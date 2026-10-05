@props(['length' => 6, 'name' => 'code'])
<x-ui.field :label="$attributes->get('label')" :error="$errors->first($name)">
    <div class="ui-otp">
        <input
            type="text"
            name="{{ $name }}"
            inputmode="numeric"
            autocomplete="one-time-code"
            pattern="[0-9]*"
            maxlength="{{ $length }}"
            aria-label="{{ $attributes->get('label', __('Verification code')) }}"
            {{ $attributes->except(['label'])->class(['ui-input']) }}
        >
    </div>
</x-ui.field>
