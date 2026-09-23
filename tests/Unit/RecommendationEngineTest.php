<?php

namespace Tests\Unit;

use App\Services\RecommendationEngine;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecommendationEngineTest extends TestCase
{
    public function test_critical_system_design_beats_lowest_skill_after_three_speaking_no_shows(): void
    {
        $result = $this->analyze($this->fixture());

        $this->assertSame(['EV_006', 'EV_SQL', 'EV_036'], array_column($result['candidates'], 'event_id'));
        $this->assertStringContainsString('System Design — 2 при требуемых 4 для Senior', $result['candidates'][0]['evidence']['skill_gap']);
        $this->assertSame(3, $result['candidates'][2]['history']['exact']);
    }

    public static function exclusions(): array
    {
        return [
            'mandatory' => [['mandatory' => true]],
            'wrong role' => [['target_roles' => ['Data Analyst']]],
            'wrong grade' => [['target_grades' => ['Junior']]],
            'unmet prerequisite' => [['prerequisites' => ['SK_SYSTEM_DESIGN' => 3]]],
            'missing prerequisite skill is zero' => [['prerequisites' => ['SK_UNKNOWN' => 1]]],
            'no future session' => [['upcoming_sessions' => ['2026-09-30']]],
            'no scheduled sessions' => [['upcoming_sessions' => []]],
            'ceiling reached' => [['develops_skills' => [['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 1, 'max_level' => 2]]]],
        ];
    }

    #[DataProvider('exclusions')]
    public function test_excludes_ineligible_or_unhelpful_events(array $override): void
    {
        $data = $this->fixture();
        $data['events'] = [array_replace($data['events'][0], $override)];

        $result = $this->analyze($data);

        $this->assertSame([], $result['candidates']);
    }

    public static function activeOrFinished(): array
    {
        return ['completed' => ['completed'], 'already started' => ['in_progress']];
    }

    #[DataProvider('activeOrFinished')]
    public function test_does_not_recommend_completed_or_started_events(string $status): void
    {
        $data = $this->fixture();
        $data['history'][] = $this->record('EV_006', $status, '2026-09-01');

        $result = $this->analyze($data);

        $this->assertNotContains('EV_006', array_column($result['candidates'], 'event_id'));
    }

    public function test_recurring_club_can_be_recommended_after_completion(): void
    {
        $data = $this->fixture();
        $data['events'] = [$data['events'][1]];
        $data['history'] = [$this->record('EV_036', 'completed', '2026-09-01')];

        $result = $this->analyze($data);

        $this->assertSame('EV_036', $result['candidates'][0]['event_id']);
    }

    public function test_projects_only_new_completions_once_and_never_reduces_levels(): void
    {
        $data = $this->fixture();
        $data['employee']['last_review_date'] = '2026-09-01';
        $data['employee']['skills']['SK_SQL'] = 5;
        $data['history'] = [
            $this->record('EV_036', 'completed', '2026-09-10'),
            $this->record('EV_036', 'completed', '2026-09-10'),
            $this->record('EV_SQL', 'completed', '2026-09-15'),
            array_replace($this->record('EV_036', 'completed', '2026-10-02'), ['record_id' => 'future']),
            array_replace($this->record('EV_036', 'completed', '2026-09-01'), ['record_id' => 'review']),
            array_replace($this->record('EV_036', 'completed', '2026-09-12'), ['record_id' => 'other', 'employee_id' => 'OTHER']),
        ];
        $data['history'][2]['record_id'] = 'sql';

        $result = $this->analyze($data);

        $this->assertSame(['SK_SYSTEM_DESIGN' => 2, 'SK_PUBLIC_SPEAKING' => 1, 'SK_SQL' => 5], $result['profile']['skills']);
        $this->assertSame(0, $data['employee']['skills']['SK_PUBLIC_SPEAKING']);
    }

    public function test_already_current_skills_are_not_reapplied(): void
    {
        $data = $this->fixture();
        $data['employee']['last_review_date'] = '2026-09-01';
        $data['history'] = [$this->record('EV_036', 'completed', '2026-09-10')];

        $result = (new RecommendationEngine)->analyze($data['employee'], $data['events'], $data['role_profiles'], $data['history'], skillsAlreadyCurrent: true);

        $this->assertSame(0, $result['profile']['skills']['SK_PUBLIC_SPEAKING']);
    }

    public function test_explicit_completion_date_takes_precedence_over_enrollment_for_skill_gains(): void
    {
        $data = $this->fixture();
        $data['employee']['last_review_date'] = '2026-09-01';
        $data['history'] = [array_replace($this->record('EV_036', 'completed', '2026-08-01'), ['completed_at' => '2026-09-15T10:00:00'])];

        $result = $this->analyze($data);

        $this->assertSame(1, $result['profile']['skills']['SK_PUBLIC_SPEAKING']);
    }

    public function test_club_in_progress_is_not_recommended_again(): void
    {
        $data = $this->fixture();
        $data['history'] = [$this->record('EV_036', 'in_progress', '2026-09-01')];

        $result = $this->analyze($data);

        $this->assertNotContains('EV_036', array_column($result['candidates'], 'event_id'));
    }

    public function test_recent_completion_can_unlock_a_prerequisite(): void
    {
        $data = $this->fixture();
        $data['employee']['skills']['SK_SYSTEM_DESIGN'] = 1;
        $data['employee']['last_review_date'] = '2026-09-01';
        $data['events'][] = array_replace($data['events'][0], ['event_id' => 'FOUNDATION', 'prerequisites' => []]);
        $data['history'][] = $this->record('FOUNDATION', 'completed', '2026-09-20');

        $result = $this->analyze($data);

        $this->assertSame('EV_006', $result['candidates'][0]['event_id']);
        $this->assertSame(2, $result['profile']['skills']['SK_SYSTEM_DESIGN']);
    }

    public function test_ceiling_and_gap_cap_the_benefit_independently(): void
    {
        $data = $this->fixture();
        $data['events'][0]['develops_skills'][0]['gain'] = 4;
        $data['events'][0]['develops_skills'][0]['max_level'] = 3;

        $result = $this->analyze($data);

        $benefit = $result['candidates'][0]['benefits'][0];
        $this->assertSame([1, 3, 1], [$benefit['gain'], $benefit['after'], $benefit['gap_closed']]);
    }

    public function test_career_goal_changes_priority_without_relaxing_role_filters(): void
    {
        $data = $this->fixture();
        $data['events'][2]['develops_skills'][0]['gain'] = 2;
        $data['employee']['career_goal'] = ['target_role' => 'Data Analyst', 'target_grade' => 'Senior'];
        $data['events'][] = array_replace($data['events'][2], ['event_id' => 'ANALYST_ONLY', 'target_roles' => ['Data Analyst']]);

        $result = $this->analyze($data);

        $this->assertSame('EV_SQL', $result['candidates'][0]['event_id']);
        $this->assertNotContains('ANALYST_ONLY', array_column($result['candidates'], 'event_id'));
        $this->assertContains('career_goal', $result['candidates'][0]['factors']);
    }

    public function test_lead_without_goal_uses_current_requirements(): void
    {
        $data = $this->fixture();
        $data['employee']['grade'] = 'Lead';

        $result = $this->analyze($data);

        $this->assertSame('Lead', $result['profile']['target_grade']);
        $this->assertStringContainsString('следующего грейда в каталоге нет', $result['candidates'][0]['evidence']['grade_requirement']);
    }

    public function test_penalty_for_related_skills_is_stronger_than_type_only(): void
    {
        $data = $this->fixture();
        $data['events'][1]['type'] = 'workshop';
        $data['events'][] = array_replace($data['events'][1], ['event_id' => 'OTHER_SPEAKING']);
        $data['history'] = [$this->record('OTHER_SPEAKING', 'declined', '2026-09-01')];

        $result = $this->analyze($data);

        $byId = array_column($result['candidates'], null, 'event_id');
        $this->assertGreaterThan($byId['EV_006']['history']['penalty'], $byId['EV_036']['history']['penalty']);
    }

    public function test_near_session_and_self_paced_availability_affect_equal_candidates(): void
    {
        $data = $this->fixture();
        $event = $data['events'][0];
        $data['events'] = [
            array_replace($event, ['event_id' => 'FAR', 'upcoming_sessions' => ['2026-12-01']]),
            array_replace($event, ['event_id' => 'NEAR', 'upcoming_sessions' => ['2026-12-01', '2026-10-02']]),
            array_replace($event, ['event_id' => 'NOW', 'format' => 'self_paced', 'upcoming_sessions' => []]),
        ];

        $result = $this->analyze($data);

        $this->assertSame(['NOW', 'NEAR', 'FAR'], array_column($result['candidates'], 'event_id'));
    }

    public function test_does_not_invent_on_time_completions_from_enrollment_date(): void
    {
        $data = $this->fixture();
        $data['history'] = [array_replace($this->record('EV_036', 'completed', '2026-09-01'), ['due_date' => '2026-09-15'])];

        $result = $this->analyze($data);

        $this->assertSame(0, $result['history']['verified_on_time']);
        $this->assertSame(1, $result['history']['unknown_timeliness']);
        $this->assertNotContains('on_time_history', $result['candidates'][0]['factors']);
    }

    public function test_uses_verified_completion_timestamps_for_timeliness(): void
    {
        $data = $this->fixture();
        $data['history'] = [array_replace($this->record('EV_036', 'completed', '2026-09-01'), ['due_date' => '2026-09-15', 'completed_at' => '2026-09-14T10:00:00'])];

        $result = $this->analyze($data);

        $this->assertSame(1, $result['history']['verified_on_time']);
        $this->assertContains('on_time_history', $result['candidates'][0]['factors']);
    }

    public function test_empty_gaps_return_no_irrelevant_recommendations(): void
    {
        $data = $this->fixture();
        $data['employee']['skills'] = ['SK_SYSTEM_DESIGN' => 5, 'SK_PUBLIC_SPEAKING' => 5, 'SK_SQL' => 5];

        $result = $this->analyze($data);

        $this->assertSame([], $result['candidates']);
    }

    public function test_equal_scores_have_stable_id_order(): void
    {
        $data = $this->fixture();
        $data['events'] = [array_replace($data['events'][0], ['event_id' => 'B']), array_replace($data['events'][0], ['event_id' => 'A'])];

        $result = $this->analyze($data);

        $this->assertSame(['A', 'B'], array_column($result['candidates'], 'event_id'));
    }

    public function test_missing_role_profile_fails_explicitly(): void
    {
        $data = $this->fixture();
        $data['role_profiles'] = [];
        $this->expectException(InvalidArgumentException::class);

        $this->analyze($data);
    }

    private function fixture(): array
    {
        return require __DIR__.'/../Fixtures/career_quest.php';
    }

    private function analyze(array $data): array
    {
        return (new RecommendationEngine)->analyze($data['employee'], $data['events'], $data['role_profiles'], $data['history'], $data['skills']);
    }

    private function record(string $eventId, string $status, string $date): array
    {
        return ['record_id' => 'NEW', 'employee_id' => 'E_TEST', 'event_id' => $eventId, 'status' => $status, 'date' => $date];
    }
}
