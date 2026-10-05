@props(['type' => 'info'])
<div role="{{ $type === 'error' || $type === 'warning' ? 'alert' : 'status' }}" {{ $attributes->class(['ui-alert', 'ui-alert--'.$type]) }}>
    {{ $slot }}
</div>
