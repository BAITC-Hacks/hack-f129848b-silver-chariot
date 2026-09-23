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
    public function __construct(private RecommendationEngine $engine, private SkillProjector $projector) {}

    public function skillGaps(): array
    {
        $profiles = RoleProfile::all()->keyBy(fn (RoleProfile $profile): string => $profile->role.'|'.$profile->grade);
        $grades = ['Junior' => 'Middle', 'Middle' => 'Senior', 'Senior' => 'Lead'];
        $totals = [];
        $events = Event::all()->keyBy('event_id')->toArray();
        foreach (Employee::with('activityRecords')->get() as $employee) {
            $history = $this->projector->history($employee->toArray(), $employee->activityRecords->toArray(), RecommendationEngine::SNAPSHOT_DATE);
            $currentSkills = $this->projector->skills($employee->toArray(), $history, $events);
            $next = $grades[$employee->grade] ?? null;
            $profile = $next ? $profiles->get($employee->role.'|'.$next) : null;
            foreach ($profile?->required_skills ?? [] as $id => $required) {
                $totals[$id] = ($totals[$id] ?? 0) + max(0, $required - ($currentSkills[$id] ?? 0));
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

    public function employeesWithoutNextStep(): Collection
    {
        $events = Event::all()->toArray();
        $profiles = RoleProfile::all()->toArray();

        return Employee::with('activityRecords')->get()->filter(function (Employee $employee) use ($events, $profiles): bool {
            $analysis = $this->engine->analyze($employee->toArray(), $events, $profiles, $employee->activityRecords->toArray());

            return $analysis['candidates'] === [];
        })->values();
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
