<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Event;
use App\Models\RoleProfile;
use App\Models\User;
use App\Services\DatasetImporter;
use App\Services\HrAnalyticsService;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.llm.driver' => 'disabled']);
        $fixture = require base_path('tests/Fixtures/career_quest.php');
        foreach ($fixture['role_profiles'] as $profile) {
            RoleProfile::create($profile);
        }
        RoleProfile::create(['role' => 'Backend Engineer', 'grade' => 'Middle', 'required_skills' => [], 'critical_skills' => []]);
        Employee::create([...$fixture['employee'], 'department' => 'Engineering', 'hire_date' => '2024-01-01', 'tenure_months' => 33, 'preferred_language' => 'ru']);
        foreach ($fixture['events'] as $event) {
            Event::create([...$event, 'description' => 'Synthetic test event.']);
        }
        $this->actingAs(User::factory()->create(['employee_id' => 'E_TEST']));
    }

    public function test_endpoint_persists_valid_llm_results_and_replaces_previous_recommendations(): void
    {
        config(['services.llm.driver' => 'openai', 'services.llm.openai.key' => 'test-key', 'services.llm.openai.base_url' => 'https://openai.test/v1']);
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) {
            $context = json_decode($request['messages'][1]['content'], true);
            $rows = array_map(fn (array $candidate): array => ['event_id' => $candidate['event_id'], 'factors' => array_keys($candidate['evidence']), 'rationale' => implode(' ', $candidate['evidence'])], $context['candidates']);

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['recommendations' => $rows])]]]]);
        }]);

        for ($i = 0; $i < 2; $i++) {
            $result = $this->postJson('/employees/E_TEST/recommendations')->assertOk()->assertJsonCount(3, 'recommendations')->assertJsonPath('recommendations.0.event_id', 'EV_006')->assertJsonPath('recommendations.0.source', 'llm');
            $this->assertDatabaseCount('recommendations', 3);
            foreach ($result->json('recommendations') as $row) {
                $this->assertDatabaseHas('recommendations', ['employee_id' => 'E_TEST', 'event_id' => $row['event_id'], 'rank' => $row['rank'], 'rationale' => $row['rationale'], 'source' => 'llm']);
            }
        }
        Http::assertSentCount(2);
    }

    public function test_unavailable_provider_falls_back_and_empty_candidates_clear_saved_results(): void
    {
        config(['services.llm.driver' => 'openai', 'services.llm.openai.key' => 'test-key', 'services.llm.openai.base_url' => 'https://openai.test/v1']);
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response([], 503)]);
        $this->postJson('/employees/E_TEST/recommendations')->assertOk()->assertJsonCount(3, 'recommendations')->assertJsonPath('recommendations.0.source', 'fallback');
        $this->assertDatabaseCount('recommendations', 3);

        Event::query()->update(['mandatory' => true]);
        $this->postJson('/employees/E_TEST/recommendations')->assertOk()->assertExactJson(['recommendations' => []]);
        $this->assertDatabaseCount('recommendations', 0);
        Http::assertSentCount(1);
    }

    public function test_completion_updates_recommendations_and_profile_without_double_counting_history(): void
    {
        $employee = Employee::findOrFail('E_TEST');
        $employee->update(['last_review_date' => '2026-09-01']);
        Event::findOrFail('EV_SQL')->update(['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 5]]]);
        $employee->activityRecords()->create(['record_id' => 'HISTORICAL', 'event_id' => 'EV_SQL', 'date' => '2026-09-15', 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);
        $this->getJson('/employees/E_TEST')->assertOk()->assertJsonPath('employee.skills.SK_SQL', 3);
        $this->assertSame(2, $employee->fresh()->skills['SK_SQL']);
        $this->postJson('/employees/E_TEST/recommendations')->assertOk()->assertJsonPath('recommendations.0.event_id', 'EV_006');

        $this->postJson('/employees/E_TEST/complete', ['event_id' => 'EV_006'])->assertOk()->assertJsonPath('skill_deltas.0.from', 2)->assertJsonPath('skill_deltas.0.to', 3);

        $this->assertDatabaseCount('recommendations', 0);
        $this->getJson('/employees/E_TEST')->assertOk()->assertJsonPath('employee.skills.SK_SQL', 3)->assertJsonPath('employee.skills.SK_SYSTEM_DESIGN', 3);
        $this->assertSame(2, $employee->activityRecords()->where('skills_applied', true)->count());
        $response = $this->postJson('/employees/E_TEST/recommendations')->assertOk();
        $this->assertNotContains('EV_006', array_column($response->json('recommendations'), 'event_id'));
        $this->postJson('/employees/E_TEST/complete', ['event_id' => 'EV_006'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_completion_never_reduces_a_skill_above_the_event_cap(): void
    {
        Employee::findOrFail('E_TEST')->update(['skills' => ['SK_PUBLIC_SPEAKING' => 5]]);
        $this->postJson('/employees/E_TEST/complete', ['event_id' => 'EV_036'])->assertOk()->assertJsonPath('skill_deltas.0.from', 5)->assertJsonPath('skill_deltas.0.to', 5);
        $this->assertSame(5, Employee::findOrFail('E_TEST')->skills['SK_PUBLIC_SPEAKING']);
    }

    public function test_reimported_assessment_resets_projection_flags_without_duplicating_history(): void
    {
        $employee = Employee::findOrFail('E_TEST');
        $original = $employee->toArray();
        app(ProgressService::class)->complete($employee, Event::findOrFail('EV_006'));
        $this->assertSame(3, $employee->fresh()->skills['SK_SYSTEM_DESIGN']);
        $file = UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [[...$original, 'manager_id' => null]]], JSON_THROW_ON_ERROR));

        app(DatasetImporter::class)->importFiles(['employees.json' => $file->getPathname()]);

        $this->assertSame(2, $employee->fresh()->skills['SK_SYSTEM_DESIGN']);
        $this->assertSame(3, app(ProgressService::class)->currentSkills($employee->fresh())['SK_SYSTEM_DESIGN']);
        $this->assertDatabaseCount('activity_records', 1);
        $this->assertDatabaseHas('activity_records', ['employee_id' => 'E_TEST', 'skills_applied' => false]);
    }

    public function test_hr_uses_engine_availability_and_useful_gain_filters(): void
    {
        $service = app(HrAnalyticsService::class);
        $this->assertCount(0, $service->employeesWithoutNextStep());
        Event::query()->where('event_id', '!=', 'EV_SQL')->update(['upcoming_sessions' => json_encode(['2026-09-30'])]);
        Event::findOrFail('EV_SQL')->update(['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 2]]]);

        $this->assertSame(['E_TEST'], $service->employeesWithoutNextStep()->pluck('employee_id')->all());
        $this->postJson('/employees/E_TEST/recommendations')->assertOk()->assertExactJson(['recommendations' => []]);
        Http::assertNothingSent();
    }
}
