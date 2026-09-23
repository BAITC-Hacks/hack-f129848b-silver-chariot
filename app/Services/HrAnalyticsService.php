<?php

namespace App\Services;

use App\Models\ActivityRecord;
use App\Models\Employee;
use App\Models\Event;
use App\Models\RoleProfile;
use App\Models\Skill;
use Illuminate\Support\Collection;

class HrAnalyticsService
{
    public function skillGaps(): array
    {
        $profiles = RoleProfile::all()->keyBy(fn (RoleProfile $profile): string => $profile->role.'|'.$profile->grade);
        $grades = ['Junior' => 'Middle', 'Middle' => 'Senior', 'Senior' => 'Lead'];
        $totals = [];
        foreach (Employee::all() as $employee) {
            $next = $grades[$employee->grade] ?? null;
            $profile = $next ? $profiles->get($employee->role.'|'.$next) : null;
            foreach ($profile?->required_skills ?? [] as $id => $required) {
                $totals[$id] = ($totals[$id] ?? 0) + max(0, $required - ($employee->skills[$id] ?? 0));
            }
        }
        arsort($totals);
        $skills = Skill::all()->keyBy('skill_id');
        $result = [];
        foreach ($totals as $id => $gap) {
            if ($gap > 0) {
                $result[] = ['skill_id' => $id, 'name' => $skills->get($id)?->name ?? $id, 'gap' => $gap];
            }
        }

        return $result;
    }

    /** Temporary candidate filtering; replace with the track B engine at integration. */
    public function employeesWithoutNextStep(): Collection
    {
        $events = Event::where('mandatory', false)->get();

        return Employee::with('activityRecords')->get()->filter(fn (Employee $employee): bool => ! $events->contains(fn (Event $event): bool => $this->isCandidate($employee, $event)))->values();
    }

    public function isCandidate(Employee $employee, Event $event): bool
    {
        if ($event->mandatory || ! in_array($employee->role, $event->target_roles, true) || ! in_array($employee->grade, $event->target_grades, true)) {
            return false;
        }
        foreach ($event->prerequisites as $id => $required) {
            if (($employee->skills[$id] ?? 0) < $required) {
                return false;
            }
        }

        return $event->event_id === 'EV_036' || ! $employee->activityRecords->contains(fn (ActivityRecord $record): bool => $record->event_id === $event->event_id && $record->status === 'completed');
    }

    public function participation(): array
    {
        $counts = ActivityRecord::selectRaw('event_id, status, count(*) as total')->groupBy('event_id', 'status')->get()->groupBy('event_id');

        return Event::all()->map(function (Event $event) use ($counts): array {
            $statuses = array_fill_keys(['completed', 'in_progress', 'dropped', 'no_show', 'declined', 'overdue'], 0);
            foreach ($counts->get($event->event_id, collect()) as $count) {
                $statuses[$count->status] = (int) $count->total;
            }

            return ['event_id' => $event->event_id, 'title' => $event->title, 'statuses' => $statuses, 'total' => array_sum($statuses)];
        })->all();
    }

    public function dashboard(): array
    {
        return ['skill_gaps' => $this->skillGaps(), 'employees_without_next_step' => $this->employeesWithoutNextStep(), 'participation' => $this->participation()];
    }
}
