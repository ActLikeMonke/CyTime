@props(['label' => null, 'variant' => null])
<fieldset {{ $attributes->class(['ui-choice-group', $variant === 'segmented' ? 'ui-choice-group--segmented' : null]) }}>
    @if ($label)<legend class="ui-legend">{{ $label }}</legend>@endif
    {{ $slot }}
</fieldset>
