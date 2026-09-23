<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Event;
use App\Models\RoleProfile;
use App\Models\Skill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FrontendIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.llm.driver' => 'disabled']);
        $this->withoutVite();
    }

    public function test_profile_renders_career_path_skills_and_participation_history(): void
    {
        $this->createProfile();

        $response = $this->withSession(['employee_id' => 'E_TEST'])->get('/employees/E_TEST')->assertOk();

        $response->assertSeeText(['Private Test Name', 'Backend Engineer', 'Junior', 'Middle', 'Senior', 'Lead', 'System Design', 'Public Speaking Club']);
        $response->assertViewHas('grade_readiness', ['grade' => 'Senior', 'covered' => 0, 'total' => 3]);
        $this->assertCount(3, $response->viewData('history'));
        $this->assertSame(['EV_006', 'EV_036', 'EV_SQL'], $response->viewData('events')->pluck('event_id')->all());
    }

    /**
     * @param  list<string>  $expectedIds
     */
    #[TestWith(['role=Backend%20Engineer', ['E_OTHER', 'E_TEST']])]
    #[TestWith(['grade=Senior', ['E_DATA', 'E_OTHER']])]
    #[TestWith(['search=Private&role=Backend%20Engineer&grade=Middle', ['E_TEST']])]
    #[TestWith(['role=Data%20Analyst&grade=Middle', []])]
    public function test_hr_employee_filters_return_only_matching_profiles(string $query, array $expectedIds): void
    {
        $employee = $this->createProfile();
        Employee::create([...$employee->toArray(), 'employee_id' => 'E_OTHER', 'full_name' => 'Private Senior Engineer', 'grade' => 'Senior']);
        Employee::create([...$employee->toArray(), 'employee_id' => 'E_DATA', 'full_name' => 'Private Data Analyst', 'role' => 'Data Analyst', 'grade' => 'Senior']);

        $response = $this->withSession(['role' => 'hr'])->get('/employees?'.$query)->assertOk();

        $response->assertViewIs('employees.index');
        $this->assertSame($expectedIds, $response->viewData('employees')->pluck('employee_id')->all());
        $response->assertSee('name="role"', false)->assertSee('name="grade"', false)->assertSee('name="search"', false);
    }

    public function test_role_switch_updates_navigation_and_restores_employee_privacy(): void
    {
        $employee = $this->createProfile();
        Employee::create([...$employee->toArray(), 'employee_id' => 'E_PRIVATE', 'full_name' => 'Other Employee Private Profile']);
        $this->withSession(['employee_id' => 'E_TEST']);

        $this->post('/session/role', ['role' => 'hr'])->assertRedirectToRoute('employees.index')->assertSessionHas('role', 'hr');
        $this->get('/employees')->assertOk()->assertSeeText('Other Employee Private Profile')->assertSee(route('hr.index'))->assertSee(route('admin.upload'));
        $this->get('/employees/E_TEST')->assertOk()->assertSeeText('К списку сотрудников');
        $this->post('/session/role', ['role' => 'employee'])->assertRedirectToRoute('employees.show', 'E_TEST')->assertSessionHas('role', 'employee');

        $this->get('/employees')->assertRedirect('/employees/E_TEST');
        $this->get('/employees/E_TEST')->assertOk()->assertSeeText('Private Test Name')->assertDontSeeText('К списку сотрудников')->assertDontSeeText('Other Employee Private Profile')->assertDontSee(route('hr.index'))->assertDontSee(route('admin.upload'));
        $this->get('/employees/E_PRIVATE')->assertNotFound();
        $this->postJson('/employees/E_PRIVATE/recommendations')->assertNotFound();
        $this->postJson('/employees/E_PRIVATE/complete', ['event_id' => 'EV_006'])->assertNotFound();
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseCount('activity_records', 3);
    }

    public function test_saved_recommendations_render_after_reload_and_completion_refreshes_profile(): void
    {
        $this->createProfile();
        Event::findOrFail('EV_006')->update(['develops_skills' => [['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 2, 'max_level' => 5]]]);
        Http::preventStrayRequests();
        $this->withSession(['employee_id' => 'E_TEST']);
        $recommendations = $this->postJson('/employees/E_TEST/recommendations')->assertOk()->json('recommendations');

        $this->get('/employees/E_TEST')->assertOk()->assertSeeText([$recommendations[0]['rationale'], 'fallback']);

        $this->postJson('/employees/E_TEST/complete', ['event_id' => 'EV_006'])
            ->assertOk()->assertJsonPath('skill_deltas.0.from', 2)->assertJsonPath('skill_deltas.0.to', 4)
            ->assertJsonPath('grade_readiness', ['grade' => 'Senior', 'covered' => 1, 'total' => 3]);
        $response = $this->get('/employees/E_TEST')->assertOk();

        $response->assertSeeText('Designing High-Load Systems')->assertDontSeeText($recommendations[0]['rationale']);
        $response->assertViewHas('grade_readiness', ['grade' => 'Senior', 'covered' => 1, 'total' => 3]);
        $this->assertSame(4, $response->viewData('employee')->skills['SK_SYSTEM_DESIGN']);
        $this->assertSame('completed', $response->viewData('history')->first()->status);
        $this->assertSame(['EV_036', 'EV_SQL'], $response->viewData('events')->pluck('event_id')->all());
        $this->assertCount(0, $response->viewData('recommendations'));
        $this->assertDatabaseHas('activity_records', ['employee_id' => 'E_TEST', 'event_id' => 'EV_006', 'status' => 'completed', 'skills_applied' => true]);
        Http::assertNothingSent();
    }

    public function test_profile_escapes_imported_employee_event_and_recommendation_text(): void
    {
        $employee = $this->createProfile();
        $name = '<script>alert("employee")</script>';
        $title = '<img src=x onerror=alert("event")>';
        $rationale = '<svg onload=alert("rationale")>Обоснование</svg>';
        $employee->update(['full_name' => $name]);
        Event::findOrFail('EV_006')->update(['title' => $title]);
        $employee->recommendations()->create(['event_id' => 'EV_006', 'rank' => 1, 'score' => 9, 'factors' => ['skill_gap', 'critical_skill', 'history_clean'], 'rationale' => $rationale, 'source' => 'fallback']);

        $response = $this->withSession(['employee_id' => 'E_TEST'])->get('/employees/E_TEST')->assertOk();

        foreach ([$name, $title, $rationale] as $value) {
            $response->assertSee($value)->assertDontSee($value, false);
        }
    }

    public function test_recurring_club_remains_available_in_profile_after_completion(): void
    {
        $employee = $this->createProfile();
        $employee->activityRecords()->create(['record_id' => 'R_COMPLETED_CLUB', 'event_id' => 'EV_036', 'date' => '2026-09-10', 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);

        $response = $this->withSession(['employee_id' => 'E_TEST'])->get('/employees/E_TEST')->assertOk();

        $response->assertViewIs('employees.show');
        $this->assertContains('EV_036', $response->viewData('events')->pluck('event_id')->all());
    }

    public function test_hr_dashboard_renders_real_skill_gaps_missing_steps_and_participation(): void
    {
        $this->createProfile();
        Event::query()->update(['mandatory' => true]);

        $response = $this->withSession(['role' => 'hr'])->get('/hr')->assertOk();

        $response->assertSeeText(['Public Speaking', 'System Design', 'Private Test Name', 'Public Speaking Club']);
        $this->assertSame(['skill_id' => 'SK_PUBLIC_SPEAKING', 'name' => 'Public Speaking', 'gap' => 3], $response->viewData('skill_gaps')[0]);
        $this->assertSame(['E_TEST'], $response->viewData('employees_without_next_step')->pluck('employee_id')->all());
        $club = collect($response->viewData('participation'))->firstWhere('event_id', 'EV_036');
        $this->assertSame(3, $club['total']);
        $this->assertSame(3, $club['statuses']['no_show']);
    }

    public function test_jury_upload_creates_visible_profile_and_history_based_recommendations(): void
    {
        $employee = $this->createProfile();
        Http::preventStrayRequests();
        $file = UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [[...$employee->toArray(), 'employee_id' => 'E_JURY', 'full_name' => 'Jury Verification Profile']]], JSON_THROW_ON_ERROR));
        $history = UploadedFile::fake()->createWithContent('activity_history.csv', "record_id,employee_id,event_id,date,due_date,status,completion_pct,score,feedback_rating,assigned_by\nJ1,E_JURY,EV_036,2026-08-01,,no_show,0,,,self\nJ2,E_JURY,EV_036,2026-08-15,,no_show,0,,,self\nJ3,E_JURY,EV_036,2026-09-01,,no_show,0,,,self\n");
        $this->withSession(['role' => 'hr']);

        $this->from('/admin/upload')->post('/admin/upload', ['employees' => $file, 'activity_history' => $history])
            ->assertRedirect('/admin/upload')->assertSessionHas('imported', ['employees' => 1, 'activity_records' => 3]);

        $this->get('/employees?search=Jury')->assertOk()->assertSeeText('Jury Verification Profile')->assertDontSeeText('Private Test Name');
        $this->get('/employees/E_JURY')->assertOk()->assertSeeText(['Jury Verification Profile', 'Public Speaking Club']);
        $response = $this->postJson('/employees/E_JURY/recommendations')->assertOk()
            ->assertJsonCount(3, 'recommendations')->assertJsonPath('recommendations.0.event_id', 'EV_006')->assertJsonPath('recommendations.0.source', 'fallback');
        foreach ($response->json('recommendations') as $recommendation) {
            $this->assertGreaterThanOrEqual(3, count($recommendation['factors']));
        }
        $this->get('/employees/E_JURY')->assertOk()->assertSeeText($response->json('recommendations.0.rationale'));
        $this->assertDatabaseHas('activity_records', ['record_id' => 'J3', 'employee_id' => 'E_JURY', 'status' => 'no_show']);
        Http::assertNothingSent();
    }

    #[TestWith(['employees'])]
    #[TestWith(['activity_history'])]
    public function test_jury_reimport_invalidates_only_affected_cached_recommendations(string $field): void
    {
        $employee = $this->createProfile();
        $other = Employee::create([...$employee->toArray(), 'employee_id' => 'E_OTHER', 'full_name' => 'Unaffected Employee']);
        foreach ([$employee, $other] as $profile) {
            $profile->recommendations()->create(['event_id' => 'EV_006', 'rank' => 1, 'score' => 9, 'factors' => ['skill_gap', 'critical_skill', 'history_clean'], 'rationale' => 'Cached before jury upload', 'source' => 'fallback']);
        }
        $file = $field === 'employees'
            ? UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [[...$employee->toArray(), 'skills' => ['SK_SYSTEM_DESIGN' => 4]]]], JSON_THROW_ON_ERROR))
            : UploadedFile::fake()->createWithContent('activity_history.csv', "record_id,employee_id,event_id,date,due_date,status,completion_pct,score,feedback_rating,assigned_by\nR1,E_TEST,EV_036,2026-08-01,,completed,100,80,4,self\n");

        $this->withSession(['role' => 'hr'])->post('/admin/upload', [$field => $file])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('recommendations', ['employee_id' => 'E_TEST']);
        $this->assertDatabaseHas('recommendations', ['employee_id' => 'E_OTHER', 'rationale' => 'Cached before jury upload']);
        $this->get('/employees/E_TEST')->assertOk()->assertDontSeeText('Cached before jury upload');
    }

    public function test_invalid_jury_upload_rolls_back_profile_changes_and_preserves_recommendations(): void
    {
        $employee = $this->createProfile();
        $employee->recommendations()->create(['event_id' => 'EV_006', 'rank' => 1, 'score' => 9, 'factors' => ['skill_gap', 'critical_skill', 'history_clean'], 'rationale' => 'Preserved recommendation', 'source' => 'fallback']);
        $file = UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [[...$employee->toArray(), 'full_name' => 'Should be rolled back']]], JSON_THROW_ON_ERROR));
        $history = UploadedFile::fake()->createWithContent('activity_history.csv', "record_id,employee_id,event_id,date,due_date,status,completion_pct,score,feedback_rating,assigned_by\nINVALID,E_TEST,UNKNOWN_EVENT,2026-09-01,,completed,100,80,4,self\n");

        $this->withSession(['role' => 'hr'])->from('/admin/upload')->post('/admin/upload', ['employees' => $file, 'activity_history' => $history])
            ->assertRedirect('/admin/upload')->assertSessionHasErrors('event_id');

        $this->assertDatabaseHas('employees', ['employee_id' => 'E_TEST', 'full_name' => 'Private Test Name']);
        $this->assertDatabaseHas('recommendations', ['employee_id' => 'E_TEST', 'rationale' => 'Preserved recommendation']);
        $this->assertDatabaseMissing('activity_records', ['record_id' => 'INVALID']);
    }

    public function test_moving_imported_history_invalidates_both_affected_employee_recommendations(): void
    {
        $employee = $this->createProfile();
        $other = Employee::create([...$employee->toArray(), 'employee_id' => 'E_OTHER', 'full_name' => 'New History Owner']);
        foreach ([$employee, $other] as $profile) {
            $profile->recommendations()->create(['event_id' => 'EV_006', 'rank' => 1, 'score' => 9, 'factors' => ['skill_gap', 'critical_skill', 'history_clean'], 'rationale' => 'Cached before history moved', 'source' => 'fallback']);
        }
        $history = UploadedFile::fake()->createWithContent('activity_history.csv', "record_id,employee_id,event_id,date,due_date,status,completion_pct,score,feedback_rating,assigned_by\nR1,E_OTHER,EV_036,2026-08-01,,no_show,0,,,self\n");

        $this->withSession(['role' => 'hr'])->post('/admin/upload', ['activity_history' => $history])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseHas('activity_records', ['record_id' => 'R1', 'employee_id' => 'E_OTHER']);
        $this->assertDatabaseMissing('activity_records', ['record_id' => 'R1', 'employee_id' => 'E_TEST']);
    }

    private function createProfile(): Employee
    {
        $fixture = require base_path('tests/Fixtures/career_quest.php');
        foreach ($fixture['role_profiles'] as $profile) {
            RoleProfile::create($profile);
        }
        RoleProfile::create(['role' => 'Backend Engineer', 'grade' => 'Middle', 'required_skills' => [], 'critical_skills' => []]);
        foreach ($fixture['skills'] as $skill) {
            Skill::create([...$skill, 'type' => 'hard', 'category' => 'Engineering', 'description' => 'Synthetic test skill.']);
        }
        foreach ($fixture['events'] as $event) {
            Event::create([...$event, 'description' => 'Synthetic test event.']);
        }
        $employee = Employee::create([...$fixture['employee'], 'department' => 'Engineering', 'manager_id' => null, 'hire_date' => '2024-01-01', 'tenure_months' => 33, 'preferred_language' => 'ru']);
        foreach ($fixture['history'] as $record) {
            $employee->activityRecords()->create([...$record, 'completion_pct' => 0, 'assigned_by' => 'self']);
        }

        return $employee;
    }
}
