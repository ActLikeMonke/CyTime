@props(['label' => null, 'hint' => null, 'error' => null, 'errorKey' => null, 'id' => null])

@php
    $inputId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $hintId = $hint ? $inputId.'-hint' : null;
    $errorId = $fieldError ? $inputId.'-error' : null;
@endphp

<x-ui.field :label="$label" :hint="$hint" :error="$fieldError" :required="$attributes->has('required')" :for="$inputId" :hint-id="$hintId" :error-id="$errorId">
    <textarea id="{{ $inputId }}" {{ $attributes->class(['ui-textarea'])->merge(['aria-describedby' => collect([$attributes->get('aria-describedby'), $hintId, $errorId])->filter()->implode(' ') ?: null, 'aria-invalid' => $fieldError ? 'true' : null]) }}>{{ $slot }}</textarea>
</x-ui.field>
