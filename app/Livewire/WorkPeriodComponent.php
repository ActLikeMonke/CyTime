<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class WorkPeriodComponent extends Component
{
    public ?int $editingEntryId = null;

    public string $startTime = '08:00';

    public string $endTime = '16:30';

    public string $breakMinutes = '30';

    public bool $isSchoolDay = false;

    /** @var array<int, array{start_time?: string, end_time?: string, break_minutes?: string}> */
    public array $additionalPeriods = [];

    abstract protected function clearRuleConfirmation(): void;

    public function addWorkPeriod(): void
    {
        if ($this->editingEntryId !== null) {
            abort(403);
        }

        $this->additionalPeriods[] = ['start_time' => '', 'end_time' => '', 'break_minutes' => '0'];
        $this->clearRuleConfirmation();
    }

    public function removeWorkPeriod(int $index): void
    {
        if (! array_key_exists($index, $this->additionalPeriods)) {
            abort(404);
        }

        unset($this->additionalPeriods[$index]);
        $this->additionalPeriods = array_values($this->additionalPeriods);
        $this->clearRuleConfirmation();
    }

    public function updatedAdditionalPeriods(): void
    {
        $this->clearRuleConfirmation();
    }

    /**
     * @return array<int, array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}>
     */
    protected function validatedWorkPeriods(string $date): array
    {
        $validated = $this->validate([
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'breakMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'isSchoolDay' => ['boolean'],
            'additionalPeriods' => ['array'],
            'additionalPeriods.*' => ['array:start_time,end_time,break_minutes'],
            'additionalPeriods.*.start_time' => ['required', 'date_format:H:i'],
            'additionalPeriods.*.end_time' => ['required', 'date_format:H:i'],
            'additionalPeriods.*.break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);
        if ($this->editingEntryId !== null && $validated['additionalPeriods'] !== []) {
            abort(403);
        }

        $periods = [[
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'break_minutes' => $validated['breakMinutes'],
        ], ...$validated['additionalPeriods']];

        return array_map(fn (array $period): array => [
            'date' => $date,
            'start_time' => $period['start_time'],
            'end_time' => $period['end_time'],
            'break_minutes' => (int) $period['break_minutes'],
            'is_school_day' => $validated['isSchoolDay'],
        ], $periods);
    }

    #[Computed]
    public function previewNetMinutes(): int
    {
        $periods = [[
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'break_minutes' => $this->breakMinutes,
        ], ...$this->additionalPeriods];
        $minutes = 0;

        foreach ($periods as $period) {
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $period['start_time'] ?? '')
                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $period['end_time'] ?? '')
                || ! ctype_digit((string) ($period['break_minutes'] ?? ''))) {
                continue;
            }

            [$startHour, $startMinute] = array_map('intval', explode(':', $period['start_time']));
            [$endHour, $endMinute] = array_map('intval', explode(':', $period['end_time']));
            $minutes += max(0, ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute) - (int) $period['break_minutes']);
        }

        return $minutes;
    }
}
