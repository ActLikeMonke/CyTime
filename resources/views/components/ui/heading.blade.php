@props(['level' => 2, 'size' => null])

@php($tag = 'h'.max(1, min(6, (int) $level)))
<{{ $tag }} {{ $attributes->class(['ui-heading', $size ? 'ui-heading--'.$size : null]) }}>{{ $slot }}</{{ $tag }}>
