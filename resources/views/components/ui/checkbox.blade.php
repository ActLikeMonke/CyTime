@props(['label' => null, 'checked' => false, 'error' => null, 'errorKey' => null, 'id' => null])

@php
    $checkboxId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $errorId = $fieldError ? $checkboxId.'-error' : null;
@endphp

<x-ui.field :error="$fieldError" :error-id="$errorId">
    <label class="ui-checkbox" for="{{ $checkboxId }}">
        <input id="{{ $checkboxId }}" type="checkbox" {{ $attributes->class(['ui-checkbox__control'])->merge(['checked' => $checked, 'aria-describedby' => $errorId, 'aria-invalid' => $fieldError ? 'true' : null]) }}>
        @if ($label)<span>{{ $label }}</span>@else{{ $slot }}@endif
    </label>
</x-ui.field>
