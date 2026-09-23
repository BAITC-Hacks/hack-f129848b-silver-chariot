<?php

namespace App\Services;

class SkillProjector
{
    /** @return list<array<string, mixed>> */
    public function history(array $employee, array $history, string $asOf): array
    {
        $records = [];
        foreach ($history as $record) {
            if (($record['employee_id'] ?? null) !== $employee['employee_id'] || ($record['date'] ?? '') > $asOf
                || (! empty($record['completed_at']) && substr($record['completed_at'], 0, 10) > $asOf)) {
                continue;
            }

            $key = $record['record_id'] ?? hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
            $records[$key] = $record;
        }

        $records = array_values($records);
        usort($records, fn (array $a, array $b): int => [$a['date'], $a['record_id'] ?? ''] <=> [$b['date'], $b['record_id'] ?? '']);

        return $records;
    }

    /**
     * Project dataset assessment levels without mutating stored employee data.
     * Pass alreadyCurrent=true if the caller has already applied completion gains.
     *
     * @param  array<string, array<string, mixed>>  $eventsById
     * @return array<string, int>
     */
    public function skills(array $employee, array $history, array $eventsById, bool $alreadyCurrent = false): array
    {
        $skills = array_map(fn ($level): int => max(0, min(5, (int) $level)), $employee['skills'] ?? []);
        if ($alreadyCurrent || empty($employee['last_review_date'])) {
            return $skills;
        }

        foreach ($history as $record) {
            $completedOn = empty($record['completed_at']) ? $record['date'] : substr($record['completed_at'], 0, 10);
            if (($record['skills_applied'] ?? false) || $record['status'] !== 'completed' || $completedOn <= $employee['last_review_date']) {
                continue;
            }

            foreach ($eventsById[$record['event_id']]['develops_skills'] ?? [] as $development) {
                $id = $development['skill_id'];
                $current = $skills[$id] ?? 0;
                $skills[$id] = $current + $this->gain($current, $development);
            }
        }

        return $skills;
    }

    public function gain(int $current, array $development): int
    {
        return max(0, min((int) $development['gain'], (int) $development['max_level'] - $current, 5 - $current));
    }
}
