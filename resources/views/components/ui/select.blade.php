@props(['label' => null, 'hint' => null, 'error' => null, 'errorKey' => null, 'id' => null])

@php
    $selectId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $hintId = $hint ? $selectId.'-hint' : null;
    $errorId = $fieldError ? $selectId.'-error' : null;
@endphp

<x-ui.field :label="$label" :hint="$hint" :error="$fieldError" :required="$attributes->has('required')" :for="$selectId" :hint-id="$hintId" :error-id="$errorId">
    <select id="{{ $selectId }}" {{ $attributes->class(['ui-select'])->merge(['aria-describedby' => collect([$attributes->get('aria-describedby'), $hintId, $errorId])->filter()->implode(' ') ?: null, 'aria-invalid' => $fieldError ? 'true' : null]) }}>
        {{ $slot }}
    </select>
</x-ui.field>
