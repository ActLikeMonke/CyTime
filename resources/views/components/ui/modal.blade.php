@props(['name', 'show' => false, 'title' => null, 'initiallyOpen' => false])
@php($wireModel = $attributes->get('wire:model'))
@php($closeHandler = $attributes->get('x-on:close'))
<dialog
    id="{{ $name }}"
    data-ui-modal="{{ $name }}"
    data-show="{{ $show ? 'true' : 'false' }}"
    @if ($title) aria-labelledby="{{ $name }}-title" @endif
    @if ($wireModel)
        x-data="{ open: $wire.entangle('{{ $wireModel }}') }"
        x-effect="open ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    @elseif ($initiallyOpen)
        x-data="{ open: @js($initiallyOpen) }"
        x-init="if (open) $el.showModal()"
    @endif
    @if ($wireModel || $closeHandler || $initiallyOpen)
        x-on:close="{{ $wireModel || $initiallyOpen ? 'open = false;' : '' }} {{ $closeHandler }}"
    @endif
    {{ $attributes->except('x-on:close')->class(['ui-modal']) }}
>
    <div class="ui-modal__content">
        @if ($title)<h2 class="ui-modal__title" id="{{ $name }}-title">{{ $title }}</h2>@endif
        @isset($body)
            <div class="ui-modal__body">{{ $body }}</div>
        @else
            <div class="ui-modal__body">{{ $slot }}</div>
        @endisset
        @isset($footer)<footer class="ui-modal__footer">{{ $footer }}</footer>@endisset
    </div>
</dialog>
