@props(['periods' => [], 'allowAdd' => true])

<x-ui.input wire:model.live="startTime" :label="__('Start time')" type="time" required />
<x-ui.input wire:model.live="endTime" :label="__('End time')" type="time" required />
<x-ui.input wire:model.live="breakMinutes" :label="__('Break within this period (minutes)')" type="number" min="0" max="1440" required />

@foreach ($periods as $index => $period)
    <fieldset class="work-period-fields" wire:key="additional-work-period-{{ $index }}">
        <legend>{{ __('Work period :number', ['number' => $index + 2]) }}</legend>
        <x-ui.input wire:model.live="additionalPeriods.{{ $index }}.start_time" :label="__('Start time')" type="time" required />
        <x-ui.input wire:model.live="additionalPeriods.{{ $index }}.end_time" :label="__('End time')" type="time" required />
        <x-ui.input wire:model.live="additionalPeriods.{{ $index }}.break_minutes" :label="__('Break within this period (minutes)')" type="number" min="0" max="1440" required />
        @foreach (['start_time', 'end_time', 'break_minutes'] as $field)
            @error("additionalPeriods.$index.$field")
                <p role="alert">{{ $message }}</p>
            @enderror
        @endforeach
        <x-ui.button type="button" wire:click="removeWorkPeriod({{ $index }})">{{ __('Remove work period :number', ['number' => $index + 2]) }}</x-ui.button>
    </fieldset>
@endforeach

@if ($allowAdd)
    <x-ui.button type="button" wire:click="addWorkPeriod">{{ __('Add another work period') }}</x-ui.button>
@endif
<x-ui.text>{{ __('Gaps between work periods count as breaks automatically. Only enter additional breaks taken within each period.') }}</x-ui.text>
