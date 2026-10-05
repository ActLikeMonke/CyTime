@props(['name' => config('app.name', 'CyTime')])

<a {{ $attributes->class(['app-brand']) }}>
    <span class="app-brand__mark" aria-hidden="true">C</span>
    <span>{{ $name }}</span>
</a>
