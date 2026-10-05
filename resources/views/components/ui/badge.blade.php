@props(['variant' => 'neutral', 'size' => null])
<span {{ $attributes->class(['ui-badge', 'ui-badge--'.$variant, $size ? 'ui-badge--'.$size : null]) }}>{{ $slot }}</span>
