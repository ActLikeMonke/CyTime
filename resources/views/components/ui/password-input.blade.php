@props(['label' => null, 'error' => null, 'errorKey' => null, 'id' => null])

@php
    $inputId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $errorId = $fieldError ? $inputId.'-error' : null;
@endphp

<x-ui.field :label="$label" :error="$fieldError" :required="$attributes->has('required')" :for="$inputId" :error-id="$errorId">
    <div class="ui-password-input" x-data="{ visible: false }">
        <input
            id="{{ $inputId }}"
            type="password"
            x-bind:type="visible ? 'text' : 'password'"
            {{ $attributes->except(['id'])->class(['ui-input'])->merge(['aria-describedby' => collect([$attributes->get('aria-describedby'), $errorId])->filter()->implode(' ') ?: null, 'aria-invalid' => $fieldError ? 'true' : null]) }}
        >
        <button
            class="ui-password-input__toggle"
            type="button"
            aria-controls="{{ $inputId }}"
            x-on:click="visible = !visible"
            x-bind:aria-label="visible ? @js(__('Hide password')) : @js(__('Show password'))"
        >
            <span x-show="!visible">{{ __('Show password') }}</span>
            <span x-show="visible">{{ __('Hide password') }}</span>
        </button>
    </div>
</x-ui.field>
