@props(['name' => '', 'initials' => null])
<span {{ $attributes->class(['ui-avatar']) }} aria-label="{{ $name }}">{{ $initials ?? Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($name, 0, 2)) }}</span>
