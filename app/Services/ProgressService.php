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

    public function __construct(private SkillProjector $projector) {}

    public function currentSkills(Employee $employee): array
    {
        $history = $this->projector->history($employee->toArray(), $employee->activityRecords()->get()->toArray(), self::SNAPSHOT_DATE);

        return $this->projector->skills($employee->toArray(), $history, Event::all()->keyBy('event_id')->toArray());
    }

    public function complete(Employee $employee, Event $event): array
    {
        return DB::transaction(function () use ($employee, $event): array {
            $employee = Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            if ($event->event_id !== 'EV_036' && $employee->activityRecords()->where('event_id', $event->event_id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['event_id' => 'This event is already completed.']);
            }
            $skills = $this->currentSkills($employee);
            $deltas = [];
            foreach ($event->develops_skills as $development) {
                $id = $development['skill_id'];
                $from = $skills[$id] ?? 0;
                $to = $from + $this->projector->gain($from, $development);
                $skills[$id] = $to;
                $deltas[] = ['skill_id' => $id, 'from' => $from, 'to' => $to];
            }
            $employee->update(['skills' => $skills]);
            $employee->activityRecords()->where('status', 'completed')
                ->where('date', '>', $employee->last_review_date->format('Y-m-d'))
                ->where('date', '<=', self::SNAPSHOT_DATE)->update(['skills_applied' => true]);
            $record = $employee->activityRecords()->make(['record_id' => 'R_'.Str::uuid(), 'event_id' => $event->event_id, 'date' => self::SNAPSHOT_DATE, 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);
            $record->forceFill(['skills_applied' => true])->save();
            $employee->recommendations()->delete();

            return ['skill_deltas' => $deltas, 'grade_readiness' => $this->gradeReadiness($employee)];
        });
    }

    public function gaps(Employee $employee): array
    {
        $profile = $employee->nextGradeProfile();
        $skills = $this->currentSkills($employee);
        $gaps = [];
        foreach ($profile?->required_skills ?? [] as $id => $required) {
            $current = $skills[$id] ?? 0;
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
