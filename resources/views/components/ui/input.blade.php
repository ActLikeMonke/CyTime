@props(['label' => null, 'hint' => null, 'error' => null, 'errorKey' => null, 'id' => null, 'viewable' => false, 'limitRange' => true])

@php
    $inputId = $id ?? $attributes->get('id') ?? 'field-'.Illuminate\Support\Str::uuid();
    $fieldName = $errorKey ?? $attributes->get('name');
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $hintId = $hint ? $inputId.'-hint' : null;
    $errorId = $fieldError ? $inputId.'-error' : null;
    $pickerType = in_array($attributes->get('type'), ['date', 'month'], true) ? $attributes->get('type') : null;
    $wrapped = $pickerType !== null || ($viewable && $attributes->get('type') === 'password');

    if ($pickerType !== null && $limitRange) {
        $rangeMin = $pickerType === 'month' ? App\Support\CalendarRange::minMonth() : App\Support\CalendarRange::minDateString();
        $rangeMax = $pickerType === 'month' ? App\Support\CalendarRange::maxMonth() : App\Support\CalendarRange::maxDateString();
        $givenMin = (string) $attributes->get('min', '');
        $givenMax = (string) $attributes->get('max', '');
        $attributes = $attributes->except(['min', 'max'])->merge([
            'min' => $givenMin !== '' && $givenMin > $rangeMin ? $givenMin : $rangeMin,
            'max' => $givenMax !== '' && $givenMax < $rangeMax ? $givenMax : $rangeMax,
        ]);
    }
@endphp

<x-ui.field :label="$label" :hint="$hint" :error="$fieldError" :required="$attributes->has('required')" :for="$inputId" :hint-id="$hintId" :error-id="$errorId">
    @if ($wrapped)
        <span @class(['ui-input-wrap', 'ui-input-wrap--picker' => $pickerType !== null])>
    @endif
        <input id="{{ $inputId }}" {{ $attributes->class(['ui-input'])->merge(['aria-describedby' => collect([$attributes->get('aria-describedby'), $hintId, $errorId])->filter()->implode(' ') ?: null, 'aria-invalid' => $fieldError ? 'true' : null]) }}>
        @if ($viewable && $attributes->get('type') === 'password')
            <button class="ui-input-wrap__toggle" type="button" data-password-toggle aria-controls="{{ $inputId }}" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}">
                <x-ui.icon name="eye" />
            </button>
        @endif
        @if ($pickerType !== null)
            <button
                class="ui-input-wrap__toggle"
                type="button"
                data-date-picker-toggle="{{ $pickerType }}"
                aria-controls="{{ $inputId }}"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-label="{{ $pickerType === 'month' ? __('Choose month') : __('Choose date') }}"
                data-previous-label="{{ $pickerType === 'month' ? __('Previous year') : __('Previous month') }}"
                data-next-label="{{ $pickerType === 'month' ? __('Next year') : __('Next month') }}"
                data-clear-label="{{ __('Clear') }}"
                data-today-label="{{ $pickerType === 'month' ? __('This month') : __('Today') }}"
                @disabled($attributes->has('disabled') && $attributes->get('disabled') !== false)
            >
                <x-ui.icon name="calendar" />
            </button>
        @endif
    @if ($wrapped)
        </span>
    @endif
</x-ui.field>
