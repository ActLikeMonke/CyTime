@props(['value', 'label' => null, 'checked' => false])
<label class="ui-radio">
    <input type="radio" value="{{ $value }}" {{ $attributes->merge(['checked' => $checked]) }}>
    @if ($label)<span>{{ $label }}</span>@else{{ $slot }}@endif
</label>
