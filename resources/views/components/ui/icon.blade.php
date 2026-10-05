@props(['name', 'class' => ''])
@php($isHidden = $attributes->get('aria-hidden', 'true'))
<x-dynamic-component :component="'heroicon-o-'.$name" {{ $attributes->except('aria-hidden')->class(['ui-icon', $class]) }} aria-hidden="{{ $isHidden }}" />
