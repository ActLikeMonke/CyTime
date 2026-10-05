@props(['label' => null, 'hint' => null, 'error' => null, 'errorKey' => null, 'id' => null, 'viewable' => false])

@php
    $inputId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $hintId = $hint ? $inputId.'-hint' : null;
    $errorId = $fieldError ? $inputId.'-error' : null;
@endphp

<x-ui.field :label="$label" :hint="$hint" :error="$fieldError" :required="$attributes->has('required')" :for="$inputId" :hint-id="$hintId" :error-id="$errorId">
    @if ($viewable && $attributes->get('type') === 'password')
        <span class="ui-input-wrap">
    @endif
        <input id="{{ $inputId }}" {{ $attributes->class(['ui-input'])->merge(['aria-describedby' => collect([$attributes->get('aria-describedby'), $hintId, $errorId])->filter()->implode(' ') ?: null, 'aria-invalid' => $fieldError ? 'true' : null]) }}>
        @if ($viewable && $attributes->get('type') === 'password')
            <button class="ui-input-wrap__toggle" type="button" data-password-toggle aria-controls="{{ $inputId }}" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}">
                <x-ui.icon name="eye" />
            </button>
        @endif
    @if ($viewable && $attributes->get('type') === 'password')
        </span>
    @endif
</x-ui.field>
