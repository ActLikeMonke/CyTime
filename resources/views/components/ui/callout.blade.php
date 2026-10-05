@props(['variant' => 'info', 'heading' => null, 'icon' => null])
@php($type = match ($variant) {'danger' => 'error', 'warning' => 'warning', 'success' => 'success', default => 'info'})
<x-ui.alert :type="$type" {{ $attributes }}>
    @if ($icon)<x-ui.icon :name="$icon" class="ui-alert__icon" />@endif
    @if ($heading)<strong>{{ $heading }}</strong>@endif
    {{ $slot }}
</x-ui.alert>
