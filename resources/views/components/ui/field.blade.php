@props(['label' => null, 'hint' => null, 'error' => null, 'required' => false, 'for' => null, 'hintId' => null, 'errorId' => null])

<div {{ $attributes->class(['ui-field']) }}>
    @if ($label)
        <label class="ui-field__label" @if ($for) for="{{ $for }}" @endif>
            {{ $label }}
            @if ($required)<span class="ui-field__required" aria-hidden="true">*</span><span class="visually-hidden">{{ __('required') }}</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($hint)<small @if ($hintId) id="{{ $hintId }}" @endif class="ui-field__hint">{{ $hint }}</small>@endif
    @if ($error)<p @if ($errorId) id="{{ $errorId }}" @endif class="ui-field__error" role="alert">{{ $error }}</p>@endif
</div>
