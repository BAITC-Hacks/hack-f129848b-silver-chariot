<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

class RecommendationEngine
{
    public const SNAPSHOT_DATE = '2026-10-01';

    private const GRADES = ['Junior', 'Middle', 'Senior', 'Lead'];

    public function __construct(private SkillProjector $projector = new SkillProjector) {}

    /**
     * All lists use the dataset schema; Eloquent callers can pass toArray().
     * The default skills input is an assessment snapshot, not a mutable live total.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $roleProfiles
     * @param  list<array<string, mixed>>  $history
     * @param  list<array<string, mixed>>  $skillCatalog
     * @return array{as_of: string, profile: array, gaps: array, history: array, candidates: array}
     */
    public function analyze(
        array $employee,
        array $events,
        array $roleProfiles,
        array $history,
        array $skillCatalog = [],
        string $asOf = self::SNAPSHOT_DATE,
        bool $skillsAlreadyCurrent = false,
    ): array {
        $gradeIndex = array_search($employee['grade'], self::GRADES, true);
        if ($gradeIndex === false) {
            throw new InvalidArgumentException('Unknown employee grade.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf);
        if ($date === false || $date->format('Y-m-d') !== $asOf) {
            throw new InvalidArgumentException('The snapshot date must be YYYY-MM-DD.');
        }

        $eventsById = array_column($events, null, 'event_id');
        $names = array_column($skillCatalog, 'name', 'skill_id');
        $records = $this->projector->history($employee, $history, $asOf);
        $skills = $this->projector->skills($employee, $records, $eventsById, $skillsAlreadyCurrent);
        $targetGrade = self::GRADES[min($gradeIndex + 1, count(self::GRADES) - 1)];
        $target = $this->roleProfile($roleProfiles, $employee['role'], $targetGrade);
        $goal = $employee['career_goal'] ?? null;
        $goalProfile = $goal === null ? null : $this->roleProfile($roleProfiles, $goal['target_role'], $goal['target_grade']);
        $historySummary = $this->summarizeHistory($records);
        $gaps = [];
        foreach ($target['required_skills'] as $id => $required) {
            $gaps[$id] = max(0, $required - ($skills[$id] ?? 0));
        }

        $candidates = [];
        foreach ($eventsById as $event) {
            $session = $this->session($event, $asOf);
            if (! $this->eligible($employee, $event, $skills, $records) || $session === null) {
                continue;
            }

            $benefits = [];
            $gapGain = $criticalGain = $goalGain = 0;
            foreach ($event['develops_skills'] as $development) {
                $id = $development['skill_id'];
                $current = $skills[$id] ?? 0;
                $gain = $this->projector->gain($current, $development);
                $required = $target['required_skills'][$id] ?? 0;
                $goalRequired = $goalProfile['required_skills'][$id] ?? 0;
                $contribution = min($gain, max(0, $required - $current));
                $goalContribution = min($gain, max(0, $goalRequired - $current));
                if ($contribution === 0 && $goalContribution === 0) {
                    continue;
                }

                $critical = in_array($id, $target['critical_skills'], true);
                $gapGain += $contribution;
                $criticalGain += $critical ? $contribution : 0;
                $goalGain += $goalContribution;
                $benefits[] = [
                    'skill_id' => $id, 'name' => $names[$id] ?? $id,
                    'current' => $current, 'required' => $required,
                    'goal_required' => $goalRequired, 'gain' => $gain,
                    'after' => $current + $gain, 'gap_closed' => $contribution,
                    'goal_gap_closed' => $goalContribution, 'critical' => $critical,
                ];
            }

            if ($benefits === []) {
                continue;
            }

            $engagement = $this->engagement($event, $records, $eventsById);
            $days = $session === 'self_paced' ? 0 : (int) $date->diff(new DateTimeImmutable($session))->days;
            $availability = $session === 'self_paced' ? 1.0 : 1 / (1 + $days / 30);
            $completionBonus = min(1.0, $engagement['completed'] * 0.2);
            $timelinessBonus = min(0.5, $historySummary['verified_on_time'] * 0.1);
            $components = [
                'skill_gap' => $gapGain * 4.0,
                'critical_skill' => $criticalGain * 3.0,
                'career_goal' => $goalGain * 2.0,
                'history_penalty' => -$engagement['penalty'],
                'completion_history' => $completionBonus,
                'on_time_history' => $timelinessBonus,
                'availability' => $availability,
            ];
            $evidence = $this->evidence($employee, $target, $goal, $benefits, $engagement, $historySummary, $session);
            $candidates[] = [
                'event_id' => $event['event_id'], 'title' => $event['title'], 'type' => $event['type'],
                'score' => round(array_sum($components), 4),
                'score_components' => $components, 'benefits' => $benefits,
                'factors' => array_keys($evidence), 'evidence' => $evidence,
                'format' => $event['format'], 'duration_hours' => $event['duration_hours'],
                'next_session' => $session, 'history' => $engagement,
            ];
        }

        usort($candidates, fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['event_id'], $b['event_id']));

        return [
            'as_of' => $asOf,
            'profile' => [
                'role' => $employee['role'], 'grade' => $employee['grade'],
                'target_grade' => $targetGrade, 'career_goal' => $goal,
                'skills' => $skills, 'work_format' => $employee['work_format'] ?? null,
            ],
            'gaps' => $gaps, 'history' => $historySummary, 'candidates' => $candidates,
        ];
    }

    private function roleProfile(array $profiles, string $role, string $grade): array
    {
        foreach ($profiles as $profile) {
            if ($profile['role'] === $role && $profile['grade'] === $grade) {
                return $profile;
            }
        }

        throw new InvalidArgumentException('Missing role profile for the employee or career goal.');
    }

    private function eligible(array $employee, array $event, array $skills, array $history): bool
    {
        if ($event['mandatory'] || ! in_array($employee['role'], $event['target_roles'], true)
            || ! in_array($employee['grade'], $event['target_grades'], true)) {
            return false;
        }

        foreach ($event['prerequisites'] as $id => $minimum) {
            if (($skills[$id] ?? 0) < $minimum) {
                return false;
            }
        }

        foreach ($history as $record) {
            if ($record['event_id'] === $event['event_id']
                && ($record['status'] === 'in_progress'
                    || ($record['status'] === 'completed' && $event['event_id'] !== 'EV_036'))) {
                return false;
            }
        }

        return true;
    }

    private function session(array $event, string $asOf): ?string
    {
        if ($event['format'] === 'self_paced') {
            return 'self_paced';
        }

        $dates = array_values(array_filter($event['upcoming_sessions'], fn (string $date): bool => $date >= $asOf));
        sort($dates, SORT_STRING);

        return $dates[0] ?? null;
    }

    private function summarizeHistory(array $records): array
    {
        $summary = ['by_status' => [], 'verified_on_time' => 0, 'verified_late' => 0, 'unknown_timeliness' => 0];
        foreach ($records as $record) {
            $status = $record['status'];
            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;
            if ($status !== 'completed') {
                continue;
            }

            if (empty($record['due_date']) || empty($record['completed_at'])) {
                $summary['unknown_timeliness']++;
            } elseif (substr($record['completed_at'], 0, 10) <= $record['due_date']) {
                $summary['verified_on_time']++;
            } else {
                $summary['verified_late']++;
            }
        }

        return $summary;
    }

    private function engagement(array $event, array $records, array $eventsById): array
    {
        $result = ['exact' => 0, 'similar_skills' => 0, 'same_type' => 0, 'completed' => 0, 'penalty' => 0.0];
        $skillIds = array_column($event['develops_skills'], 'skill_id');
        foreach ($records as $record) {
            $past = $eventsById[$record['event_id']] ?? null;
            if ($past === null || $past['mandatory']) {
                continue;
            }

            $similar = array_intersect($skillIds, array_column($past['develops_skills'], 'skill_id')) !== [];
            if ($record['status'] === 'completed' && $similar) {
                $result['completed']++;
            }
            if (! in_array($record['status'], ['no_show', 'declined', 'dropped'], true)) {
                continue;
            }

            if ($record['event_id'] === $event['event_id']) {
                $result['exact']++;
                $result['penalty'] += 4;
            } elseif ($similar) {
                $result['similar_skills']++;
                $result['penalty'] += 2;
            } elseif ($past['type'] === $event['type']) {
                $result['same_type']++;
                $result['penalty'] += 0.5;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function evidence(array $employee, array $target, ?array $goal, array $benefits, array $history, array $summary, string $session): array
    {
        $role = $employee['role'];
        $grade = $employee['grade'];
        $targetGrade = $target['grade'];
        $evidence = [
            'grade_requirement' => $grade === 'Lead'
                ? "Текущая роль — {$role}, грейд Lead; оцениваем соответствие требованиям Lead, следующего грейда в каталоге нет."
                : "Текущая роль — {$role}, грейд {$grade}; следующий грейд — {$targetGrade}.",
        ];
        $gapSentences = [];
        $criticalNames = [];
        foreach ($benefits as $benefit) {
            $required = $benefit['gap_closed'] > 0 ? $benefit['required'] : $benefit['goal_required'];
            $requiredGrade = $benefit['gap_closed'] > 0 ? $targetGrade : $goal['target_grade'];
            $gapSentences[] = "{$benefit['name']} — {$benefit['current']} при требуемых {$required} для {$requiredGrade}; после выполнения — {$benefit['after']} (+{$benefit['gain']}).";
            if ($benefit['critical'] && $benefit['gap_closed'] > 0) {
                $criticalNames[] = $benefit['name'];
            }
        }
        $evidence['skill_gap'] = implode(' ', $gapSentences);
        if ($criticalNames !== []) {
            $evidence['critical_skill'] = 'Для грейда '.$targetGrade.' критичны развиваемые навыки: '.implode(', ', $criticalNames).'.';
        }

        if ($history['penalty'] > 0) {
            $evidence['history_penalty'] = "Пропуски, отказы и прекращения: эта активность — {$history['exact']}, другие с общими навыками — {$history['similar_skills']}, только с тем же типом — {$history['same_type']}; это снижает приоритет.";
        } else {
            $evidence['history_clean'] = 'В доступной истории нет пропусков, отказов или прекращений этой активности, активностей с общими навыками или того же типа.';
        }
        if ($history['completed'] > 0) {
            $evidence['completion_history'] = "Завершено добровольных активностей с общими навыками: {$history['completed']}.";
        }
        if ($summary['verified_on_time'] > 0) {
            $evidence['on_time_history'] = "Подтверждено завершений в срок по дате завершения и дедлайну: {$summary['verified_on_time']}.";
        }
        if ($goal !== null && array_sum(array_column($benefits, 'goal_gap_closed')) > 0) {
            $evidence['career_goal'] = "Активность сокращает разрыв до карьерной цели: {$goal['target_role']}, {$goal['target_grade']}.";
        }
        $evidence['availability'] = $session === 'self_paced'
            ? 'Формат self_paced: можно начать в любое время.'
            : "Ближайшая доступная сессия — {$session}.";

        return $evidence;
    }
}
