@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
    'leadingIcon' => null,
])

@php
    $classes = ['ui-button', 'ui-button--'.$variant, 'ui-button--'.$size];
    $isDisabled = $disabled || $loading;
@endphp

@if ($href)
    <a href="{{ $isDisabled ? null : $href }}" {{ $attributes->class($classes)->merge(['aria-disabled' => $isDisabled ? 'true' : null, 'tabindex' => $isDisabled ? '-1' : null]) }}>
        @if ($leadingIcon)<x-ui.icon :name="$leadingIcon" class="ui-button__icon" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($loading)<span class="ui-spinner" aria-hidden="true"></span><span class="visually-hidden">{{ __('Loading') }}</span>@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes)->merge(['disabled' => $isDisabled]) }}>
        @if ($leadingIcon)<x-ui.icon :name="$leadingIcon" class="ui-button__icon" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($loading)<span class="ui-spinner" aria-hidden="true"></span><span class="visually-hidden">{{ __('Loading') }}</span>@endif
    </button>
@endif
