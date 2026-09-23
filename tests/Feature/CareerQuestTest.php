<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Event;
use App\Models\RoleProfile;
use App\Services\DatasetImporter;
use App\Services\HrAnalyticsService;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CareerQuestTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function import(): void
    {
        app(DatasetImporter::class)->import(base_path('docs/case_1/career_quest_dataset'));
    }

    public function test_import_is_repeatable_with_exact_dataset_counts(): void
    {
        $this->artisan('data:import')->assertSuccessful();
        $this->artisan('data:import')->assertSuccessful();
        foreach (['skills' => 60, 'role_profiles' => 32, 'employees' => 200, 'events' => 40, 'activity_records' => 2743] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $employee = Employee::findOrFail('E0001');
        $this->assertSame('E0050', $employee->manager->employee_id);
        $this->assertSame('Junior', $employee->roleProfile()->grade);
        $this->assertSame('Middle', $employee->nextGradeProfile()->grade);
    }

    public function test_complete_applies_caps_missing_skills_snapshot_and_exact_contract(): void
    {
        $this->import();
        $employee = Employee::findOrFail('E0001');
        $employee->update(['skills' => ['SK_PYTHON' => 3, 'SK_SQL' => 4], 'last_review_date' => ProgressService::SNAPSHOT_DATE]);
        RoleProfile::where('role', $employee->role)->where('grade', 'Middle')->update(['required_skills' => json_encode(['SK_PYTHON' => 4, 'SK_SQL' => 5, 'SK_SYSTEM_DESIGN' => 3])]);
        $event = Event::findOrFail('EV_036');
        $event->update(['develops_skills' => [['skill_id' => 'SK_PYTHON', 'gain' => 3, 'max_level' => 4], ['skill_id' => 'SK_SQL', 'gain' => 3, 'max_level' => 5], ['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 2, 'max_level' => 4]]]);
        $this->postJson('/employees/E0001/complete', ['event_id' => 'EV_036'])->assertOk()->assertExactJson(['skill_deltas' => [['skill_id' => 'SK_PYTHON', 'from' => 3, 'to' => 4], ['skill_id' => 'SK_SQL', 'from' => 4, 'to' => 5], ['skill_id' => 'SK_SYSTEM_DESIGN', 'from' => 0, 'to' => 2]], 'grade_readiness' => ['grade' => 'Middle', 'covered' => 2, 'total' => 3]]);
        $this->assertSame(['SK_PYTHON' => 4, 'SK_SQL' => 5, 'SK_SYSTEM_DESIGN' => 2], $employee->fresh()->skills);
        $this->assertDatabaseHas('activity_records', ['employee_id' => 'E0001', 'event_id' => 'EV_036', 'date' => '2026-10-01', 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);
    }

    public function test_completed_events_are_rejected_except_recurring_club(): void
    {
        $this->import();
        $employee = Employee::findOrFail('E0001');
        $record = $employee->activityRecords()->where('status', 'completed')->where('event_id', '!=', 'EV_036')->firstOrFail();
        $this->postJson('/employees/E0001/complete', ['event_id' => $record->event_id])->assertUnprocessable()->assertJsonValidationErrors('event_id');
        $this->assertDatabaseCount('activity_records', 2743);
        $service = app(ProgressService::class);
        $event = Event::findOrFail('EV_036');
        $service->complete($employee, $event);
        $service->complete($employee, $event);
        $this->assertDatabaseCount('activity_records', 2745);
    }

    public function test_employee_access_and_recommendations(): void
    {
        config(['services.llm.driver' => 'disabled']);
        $this->import();
        $this->get('/employees')->assertSee('Marat Yessenov');
        $this->get('/employees/E0001')->assertOk();
        $this->getJson('/employees')->assertJsonPath('employees.total', 1);
        $this->get('/employees/E0002')->assertNotFound();
        $this->postJson('/employees/E0002/complete', ['event_id' => 'EV_036'])->assertNotFound();
        $this->postJson('/employees/E0002/recommendations')->assertNotFound();
        $this->get('/hr')->assertForbidden();
        $this->get('/admin/upload')->assertForbidden();
        $this->postJson('/admin/upload')->assertForbidden();
        $this->postJson('/employees/E0001/recommendations')->assertOk()->assertJsonPath('recommendations.0.source', 'fallback');
        $this->postJson('/employees/E0001/complete', [])->assertUnprocessable()->assertJsonValidationErrors('event_id');
        $this->postJson('/employees/E0001/complete', ['event_id' => 'missing'])->assertUnprocessable();
    }

    public function test_hr_session_search_and_views(): void
    {
        $this->import();
        $this->postJson('/session/role', ['role' => 'hr'])->assertExactJson(['role' => 'hr'])->assertSessionHas('role', 'hr');
        $this->get('/hr')->assertOk();
        $this->get('/admin/upload')->assertOk();
        $this->getJson('/employees?search=Marat%20Yessenov')->assertJsonPath('employees.total', 1);
        $this->get('/employees/E0002')->assertOk();
        $this->postJson('/session/role', ['role' => 'administrator'])->assertUnprocessable();
        $this->postJson('/session/role', ['role' => 'employee'])->assertOk();
        $this->get('/hr')->assertForbidden();
    }

    public function test_employee_only_upload_merges_new_and_existing_records(): void
    {
        $this->import();
        $data = json_decode(file_get_contents(base_path('docs/case_1/career_quest_dataset/employees.json')), true);
        $employee = $data['employees'][0];
        $employee['full_name'] = 'Updated name';
        $new = $employee;
        $new['employee_id'] = 'JURY001';
        $file = UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [$employee, $new]]));
        $this->withSession(['role' => 'hr'])->postJson('/admin/upload', ['employees' => $file])->assertOk()->assertExactJson(['imported' => ['employees' => 2]]);
        $this->assertDatabaseCount('employees', 201);
        $this->assertDatabaseHas('employees', ['employee_id' => 'E0001', 'full_name' => 'Updated name']);
        $this->assertDatabaseHas('employees', ['employee_id' => 'JURY001', 'manager_id' => 'E0050']);
    }

    public function test_history_only_upload_upserts_and_bad_combined_upload_rolls_back(): void
    {
        $this->import();
        $csv = "record_id,employee_id,event_id,date,due_date,status,completion_pct,score,feedback_rating,assigned_by\nR_JURY,E0001,EV_036,2026-09-30,,completed,100,,,self\n";
        for ($i = 0; $i < 2; $i++) {
            $this->withSession(['role' => 'hr'])->postJson('/admin/upload', ['activity_history' => UploadedFile::fake()->createWithContent('activity_history.csv', $csv)])->assertOk();
        }
        $this->assertDatabaseCount('activity_records', 2744);
        $data = json_decode(file_get_contents(base_path('docs/case_1/career_quest_dataset/employees.json')), true)['employees'][0];
        $data['full_name'] = 'Should roll back';
        $this->postJson('/admin/upload', ['employees' => UploadedFile::fake()->createWithContent('employees.json', json_encode(['employees' => [$data]])), 'activity_history' => UploadedFile::fake()->createWithContent('activity_history.csv', str_replace('E0001', 'MISSING', $csv))])->assertUnprocessable();
        $this->assertDatabaseHas('employees', ['employee_id' => 'E0001', 'full_name' => 'Marat Yessenov']);
        $this->postJson('/admin/upload', ['employees' => UploadedFile::fake()->createWithContent('employees.json', '{}')])->assertUnprocessable();
        $this->postJson('/admin/upload', ['employees' => UploadedFile::fake()->createWithContent('employees.json', 'broken')])->assertUnprocessable();
        $this->postJson('/admin/upload', [])->assertUnprocessable();
    }

    public function test_analytics_aggregate_gaps_and_candidate_filters(): void
    {
        $this->import();
        $employee = Employee::findOrFail('E0001');
        $employee->update(['skills' => [], 'last_review_date' => ProgressService::SNAPSHOT_DATE]);
        Employee::where('employee_id', '!=', 'E0001')->update(['grade' => 'Lead']);
        RoleProfile::where('role', $employee->role)->where('grade', 'Middle')->update(['required_skills' => json_encode(['SK_PYTHON' => 4])]);
        $service = app(HrAnalyticsService::class);
        $this->assertSame([['skill_id' => 'SK_PYTHON', 'name' => 'Python', 'gap' => 4]], $service->skillGaps());
        $this->assertSame(2743, array_sum(array_column($service->participation(), 'total')));
        $this->assertCount(40, $service->participation());
        Event::query()->update(['mandatory' => true]);
        $this->assertCount(200, $service->employeesWithoutNextStep());
        $event = Event::findOrFail('EV_036');
        $event->update(['mandatory' => false, 'target_roles' => [$employee->role], 'target_grades' => ['Junior'], 'prerequisites' => [], 'develops_skills' => [['skill_id' => 'SK_PYTHON', 'gain' => 1, 'max_level' => 4]]]);
        $this->assertCount(199, $service->employeesWithoutNextStep());
        $event->update(['prerequisites' => ['SK_PYTHON' => 1]]);
        $this->assertCount(200, $service->employeesWithoutNextStep());
        $lead = Employee::findOrFail('E0002');
        $this->assertSame(['grade' => null, 'covered' => 0, 'total' => 0], app(ProgressService::class)->gradeReadiness($lead));
    }
}
