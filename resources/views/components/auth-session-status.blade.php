@props([
    'status',
])

@if ($status)
    <div {{ $attributes->class(['ui-alert', 'ui-alert--success']) }} role="status">
        {{ $status }}
    </div>
@endif
