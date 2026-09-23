<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    public const SNAPSHOT_DATE = '2026-10-01';

    public function complete(Employee $employee, Event $event): array
    {
        return DB::transaction(function () use ($employee, $event): array {
            $employee = Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            if ($event->event_id !== 'EV_036' && $employee->activityRecords()->where('event_id', $event->event_id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['event_id' => 'This event is already completed.']);
            }
            $skills = $employee->skills;
            $deltas = [];
            foreach ($event->develops_skills as $development) {
                $id = $development['skill_id'];
                $from = $skills[$id] ?? 0;
                $to = min($from + $development['gain'], $development['max_level'], 5);
                $skills[$id] = $to;
                $deltas[] = ['skill_id' => $id, 'from' => $from, 'to' => $to];
            }
            $employee->update(['skills' => $skills]);
            $employee->activityRecords()->create(['record_id' => 'R_'.Str::uuid(), 'event_id' => $event->event_id, 'date' => self::SNAPSHOT_DATE, 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);

            return ['skill_deltas' => $deltas, 'grade_readiness' => $this->gradeReadiness($employee)];
        });
    }

    public function gaps(Employee $employee): array
    {
        $profile = $employee->nextGradeProfile();
        $gaps = [];
        foreach ($profile?->required_skills ?? [] as $id => $required) {
            $current = $employee->skills[$id] ?? 0;
            $gaps[] = ['skill_id' => $id, 'current' => $current, 'required' => $required, 'gap' => max(0, $required - $current), 'critical' => in_array($id, $profile->critical_skills, true)];
        }

        return $gaps;
    }

    public function gradeReadiness(Employee $employee): array
    {
        $gaps = $this->gaps($employee);

        return ['grade' => $employee->nextGradeProfile()?->grade, 'covered' => count(array_filter($gaps, fn (array $gap): bool => $gap['gap'] === 0)), 'total' => count($gaps)];
    }
}
