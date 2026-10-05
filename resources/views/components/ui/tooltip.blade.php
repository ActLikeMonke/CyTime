@props(['content'])
@php($tooltipId = 'tooltip-'.Illuminate\Support\Str::uuid())
<span class="ui-tooltip" tabindex="0" aria-describedby="{{ $tooltipId }}">
    {{ $slot }}
    <span id="{{ $tooltipId }}" class="ui-tooltip__content" role="tooltip">{{ $content }}</span>
</span>
