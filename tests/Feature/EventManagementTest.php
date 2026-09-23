<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Event;
use App\Models\Recommendation;
use App\Models\RoleProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_hr_can_create_scheduled_activity_and_edit_its_persisted_fields(): void
    {
        $this->createCatalog();
        $this->createCachedRecommendations();
        $payload = [...$this->validPayload(), 'event_id' => 'EV_INJECTED', 'mandatory' => '1'];
        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->get('/hr/events/create')->assertOk()->assertViewIs('events.form');

        $response = $this->post('/hr/events', $payload)->assertSessionHasNoErrors();

        $event = Event::where('title', 'Architecture Practice')->sole();
        $response->assertRedirectToRoute('hr.events.edit', $event);
        $this->assertStringStartsWith('EV_', $event->event_id);
        $this->assertNotSame('EV_INJECTED', $event->event_id);
        $this->assertSame('Practice designing reliable services.', $event->description);
        $this->assertSame('workshop', $event->type);
        $this->assertSame('online', $event->format);
        $this->assertSame(2.5, $event->duration_hours);
        $this->assertTrue($event->mandatory);
        $this->assertSame(['Backend Engineer'], $event->target_roles);
        $this->assertSame(['Middle', 'Senior'], $event->target_grades);
        $this->assertSame([['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 2, 'max_level' => 5]], $event->develops_skills);
        $this->assertSame(['SK_SQL' => 0], $event->prerequisites);
        $this->assertSame(['2026-11-03', '2026-12-20'], $event->upcoming_sessions);
        $this->assertDatabaseCount('events', 4);
        $this->assertDatabaseCount('recommendations', 0);
        $this->get('/hr/events/'.$event->event_id.'/edit')->assertOk()->assertViewIs('events.form')
            ->assertSee('Architecture Practice')->assertSee('2026-11-03')->assertSee('2026-12-20');
    }

    public function test_hr_can_change_activity_to_self_paced_without_changing_its_id(): void
    {
        $this->createCatalog();
        $this->createCachedRecommendations();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->patch('/hr/events/EV_006', [
            ...$this->validPayload(), 'event_id' => 'EV_REPLACEMENT', 'title' => 'Self-paced Architecture',
            'format' => 'self_paced', 'type' => 'course',
            'develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => '1', 'max_level' => '4']],
        ])->assertRedirectToRoute('hr.events.edit', 'EV_006')->assertSessionHasNoErrors();

        $event = Event::findOrFail('EV_006');
        $this->assertSame('Self-paced Architecture', $event->title);
        $this->assertSame('self_paced', $event->format);
        $this->assertSame('course', $event->type);
        $this->assertFalse($event->mandatory);
        $this->assertSame([], $event->upcoming_sessions);
        $this->assertSame([['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 4]], $event->develops_skills);
        $this->assertDatabaseMissing('events', ['event_id' => 'EV_REPLACEMENT']);
        $this->assertDatabaseCount('events', 3);
        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_hr_can_delete_unused_activity_and_clear_all_cached_recommendations(): void
    {
        $this->createCatalog();
        $this->createCachedRecommendations();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->delete('/hr/events/EV_006')
            ->assertRedirectToRoute('hr.events.index')->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('events', ['event_id' => 'EV_006']);
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('recommendations', 0);
    }

    /**
     * @param  list<string>  $expectedIds
     */
    #[TestWith(['search=High-Load', ['EV_006']])]
    #[TestWith(['search=EV_SQL', ['EV_SQL']])]
    #[TestWith(['format=self_paced', ['EV_SQL']])]
    #[TestWith(['type=meetup', ['EV_036']])]
    #[TestWith(['search=SQL&format=online', []])]
    public function test_hr_catalog_filters_return_only_matching_activities(string $query, array $expectedIds): void
    {
        $this->createCatalog();

        $response = $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->get('/hr/events?'.$query)->assertOk();

        $response->assertViewIs('events.index');
        $this->assertSame($expectedIds, $response->viewData('events')->pluck('event_id')->all());
        $response->assertSee('name="search"', false)->assertSee('name="format"', false)->assertSee('name="type"', false);
    }

    #[TestWith(['GET', '/hr/events'])]
    #[TestWith(['GET', '/hr/events/create'])]
    #[TestWith(['POST', '/hr/events'])]
    #[TestWith(['GET', '/hr/events/EV_006/edit'])]
    #[TestWith(['PATCH', '/hr/events/EV_006'])]
    #[TestWith(['DELETE', '/hr/events/EV_006'])]
    public function test_employee_receives_403_from_each_activity_management_endpoint(string $method, string $uri): void
    {
        $this->createCatalog();

        $this->actingAs(User::factory()->create())->withSession(['role' => 'employee'])->json($method, $uri, $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('events', 3);
        $this->assertDatabaseHas('events', ['event_id' => 'EV_006', 'title' => 'Designing High-Load Systems']);
    }

    #[TestWith(['GET', '/hr/events/MISSING/edit'])]
    #[TestWith(['PATCH', '/hr/events/MISSING'])]
    #[TestWith(['DELETE', '/hr/events/MISSING'])]
    public function test_hr_receives_404_for_missing_activity(string $method, string $uri): void
    {
        $this->createCatalog();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->json($method, $uri, $this->validPayload())->assertNotFound();

        $this->assertDatabaseCount('events', 3);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidActivityPayloads')]
    public function test_invalid_activity_returns_422_without_changing_catalog(array $overrides, string $error): void
    {
        $this->createCatalog();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->postJson('/hr/events', [...$this->validPayload(), ...$overrides])
            ->assertUnprocessable()->assertJsonValidationErrors($error);

        $this->assertDatabaseCount('events', 3);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidActivityPayloads(): array
    {
        return [
            'missing title' => [['title' => ''], 'title'],
            'unknown type' => [['type' => 'retreat'], 'type'],
            'unknown format' => [['format' => 'hybrid'], 'format'],
            'zero duration' => [['duration_hours' => 0], 'duration_hours'],
            'duration below storage precision' => [['duration_hours' => 0.001], 'duration_hours'],
            'duration exceeds database capacity' => [['duration_hours' => 1000000], 'duration_hours'],
            'unknown role' => [['target_roles' => ['Invented Role']], 'target_roles.0'],
            'no selected roles' => [['target_roles' => []], 'target_roles'],
            'unknown grade' => [['target_grades' => ['Principal']], 'target_grades.0'],
            'no selected grades' => [['target_grades' => []], 'target_grades'],
            'scheduled without sessions' => [['upcoming_sessions' => []], 'upcoming_sessions'],
            'invalid calendar date' => [['upcoming_sessions' => ['2026-02-30']], 'upcoming_sessions.0'],
            'timestamp instead of date' => [['upcoming_sessions' => ['2026-11-03T10:00:00']], 'upcoming_sessions.0'],
            'duplicate dates' => [['upcoming_sessions' => ['2026-11-03', '2026-11-03']], 'upcoming_sessions.0'],
            'unknown developed skill' => [['develops_skills' => [['skill_id' => 'SK_UNKNOWN', 'gain' => 1, 'max_level' => 5]]], 'develops_skills.0.skill_id'],
            'zero gain' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 0, 'max_level' => 5]]], 'develops_skills.0.gain'],
            'fractional gain' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1.5, 'max_level' => 5]]], 'develops_skills.0.gain'],
            'gain exceeds scale' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 6, 'max_level' => 5]]], 'develops_skills.0.gain'],
            'cap exceeds scale' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 6]]], 'develops_skills.0.max_level'],
            'incomplete developed skill' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1]]], 'develops_skills.0.max_level'],
            'duplicate developed skill' => [['develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 4], ['skill_id' => 'SK_SQL', 'gain' => 2, 'max_level' => 5]]], 'develops_skills.0.skill_id'],
            'unknown prerequisite skill' => [['prerequisite_skills' => [['skill_id' => 'SK_UNKNOWN', 'min_level' => 1]]], 'prerequisite_skills.0.skill_id'],
            'prerequisite below scale' => [['prerequisite_skills' => [['skill_id' => 'SK_SQL', 'min_level' => -1]]], 'prerequisite_skills.0.min_level'],
            'prerequisite exceeds scale' => [['prerequisite_skills' => [['skill_id' => 'SK_SQL', 'min_level' => 6]]], 'prerequisite_skills.0.min_level'],
            'incomplete prerequisite' => [['prerequisite_skills' => [['skill_id' => 'SK_SQL']]], 'prerequisite_skills.0.min_level'],
            'duplicate prerequisite skill' => [['prerequisite_skills' => [['skill_id' => 'SK_SQL', 'min_level' => 1], ['skill_id' => 'SK_SQL', 'min_level' => 2]]], 'prerequisite_skills.0.skill_id'],
        ];
    }

    public function test_blank_optional_rows_are_ignored_and_self_paced_activity_needs_no_dates(): void
    {
        $this->createCatalog();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->post('/hr/events', [
            ...$this->validPayload(), 'format' => 'self_paced',
            'develops_skills' => [['skill_id' => '', 'gain' => '', 'max_level' => '']],
            'prerequisite_skills' => [['skill_id' => '', 'min_level' => '']],
            'upcoming_sessions' => [''],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $event = Event::where('title', 'Architecture Practice')->sole();
        $this->assertSame([], $event->develops_skills);
        $this->assertSame([], $event->prerequisites);
        $this->assertSame([], $event->upcoming_sessions);
        $this->assertFalse($event->mandatory);
    }

    #[TestWith(['completed'])]
    #[TestWith(['no_show'])]
    public function test_activity_with_participation_history_cannot_be_deleted(string $status): void
    {
        $this->createCatalog();
        $employee = $this->createCachedRecommendations();
        $employee->activityRecords()->create(['record_id' => 'R_PROTECTED', 'event_id' => 'EV_006', 'date' => '2026-10-07', 'status' => $status, 'completion_pct' => 0, 'assigned_by' => 'self']);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->from('/hr/events/EV_006/edit')->delete('/hr/events/EV_006')
            ->assertRedirect('/hr/events/EV_006/edit')->assertSessionHasErrors(['event' => 'Нельзя удалить активность с историей участия сотрудников.']);

        $this->assertDatabaseHas('events', ['event_id' => 'EV_006']);
        $this->assertDatabaseHas('activity_records', ['record_id' => 'R_PROTECTED', 'status' => $status]);
        $this->assertDatabaseCount('recommendations', 2);
    }

    #[TestWith(['completed'])]
    #[TestWith(['no_show'])]
    public function test_skill_changes_with_participation_history_return_422_and_preserve_records(string $status): void
    {
        $this->createCatalog();
        $employee = $this->createCachedRecommendations();
        $employee->activityRecords()->create(['record_id' => 'R_PROTECTED', 'event_id' => 'EV_006', 'date' => '2026-10-07', 'status' => $status, 'completion_pct' => 0, 'assigned_by' => 'self']);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->patchJson('/hr/events/EV_006', $this->validPayload())
            ->assertUnprocessable()->assertJsonValidationErrors([
                'develops_skills' => 'Нельзя менять прирост навыков у активности с историей участия: это изменит прогресс сотрудников. Создайте новую активность.',
            ]);

        $event = Event::findOrFail('EV_006');
        $this->assertSame('Designing High-Load Systems', $event->title);
        $this->assertSame([['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 1, 'max_level' => 5]], $event->develops_skills);
        $this->assertDatabaseHas('activity_records', ['record_id' => 'R_PROTECTED', 'status' => $status]);
        $this->assertDatabaseCount('recommendations', 2);
    }

    public function test_other_fields_can_change_with_history_while_omitted_developments_are_preserved(): void
    {
        $this->createCatalog();
        $employee = $this->createCachedRecommendations();
        $employee->activityRecords()->create(['record_id' => 'R_PROTECTED', 'event_id' => 'EV_006', 'date' => '2026-10-07', 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);
        $payload = $this->validPayload();
        unset($payload['develops_skills']);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->patch('/hr/events/EV_006', $payload)
            ->assertRedirectToRoute('hr.events.edit', 'EV_006')->assertSessionHasNoErrors();

        $event = Event::findOrFail('EV_006');
        $this->assertSame('Architecture Practice', $event->title);
        $this->assertSame(['2026-11-03', '2026-12-20'], $event->upcoming_sessions);
        $this->assertSame([['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 1, 'max_level' => 5]], $event->develops_skills);
        $this->assertDatabaseHas('activity_records', ['record_id' => 'R_PROTECTED', 'status' => 'completed']);
        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_historical_activity_edit_form_locks_developments_and_hides_delete_action(): void
    {
        $this->createCatalog();
        $employee = $this->createCachedRecommendations();
        $employee->activityRecords()->create(['record_id' => 'R_PROTECTED', 'event_id' => 'EV_006', 'date' => '2026-10-07', 'status' => 'completed', 'completion_pct' => 100, 'assigned_by' => 'self']);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->get('/hr/events/EV_006/edit')
            ->assertSee('data-row-group="develops_skills" disabled', false)
            ->assertSeeText('Удаление недоступно: у активности есть история участия сотрудников.')
            ->assertDontSee('data-event-delete', false);
    }

    public function test_invalid_edit_keeps_cleared_lists_empty_instead_of_restoring_saved_values(): void
    {
        $this->createCatalog();
        $payload = [...$this->validPayload(), 'title' => ''];
        unset($payload['develops_skills'], $payload['prerequisite_skills'], $payload['target_roles'], $payload['target_grades'], $payload['upcoming_sessions']);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->from('/hr/events/EV_006/edit')->patch('/hr/events/EV_006', $payload)
            ->assertRedirect('/hr/events/EV_006/edit')->assertSessionHasErrors([
                'title' => 'Заполните поле «Название».', 'target_roles', 'target_grades', 'upcoming_sessions',
            ]);

        $this->get('/hr/events/EV_006/edit')->assertOk()
            ->assertDontSee('name="develops_skills[0][skill_id]"', false)
            ->assertDontSee('name="prerequisite_skills[0][skill_id]"', false)
            ->assertDontSee('name="upcoming_sessions[0]"', false)
            ->assertDontSee('value="Backend Engineer" checked', false)
            ->assertDontSee('value="Middle" checked', false);
        $event = Event::findOrFail('EV_006');
        $this->assertSame([['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 1, 'max_level' => 5]], $event->develops_skills);
        $this->assertSame(['SK_SYSTEM_DESIGN' => 2], $event->prerequisites);
        $this->assertSame(['2026-10-07'], $event->upcoming_sessions);
    }

    public function test_invalid_update_preserves_activity_and_cached_recommendations(): void
    {
        $this->createCatalog();
        $this->createCachedRecommendations();

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->patchJson('/hr/events/EV_006', [...$this->validPayload(), 'upcoming_sessions' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('upcoming_sessions');

        $this->assertDatabaseHas('events', ['event_id' => 'EV_006', 'title' => 'Designing High-Load Systems']);
        $this->assertDatabaseCount('recommendations', 2);
    }

    public function test_catalog_and_edit_form_escape_activity_content(): void
    {
        $this->createCatalog();
        $title = '<script>alert("title")</script>';
        $description = '<img src=x onerror=alert("description")>';
        Event::findOrFail('EV_006')->update(['title' => $title, 'description' => $description]);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->get('/hr/events')->assertSee($title)->assertDontSee($title, false);
        $this->get('/hr/events/EV_006/edit')->assertSee($title)->assertSee($description)
            ->assertDontSee($title, false)->assertDontSee($description, false);
    }

    #[TestWith(['update'])]
    #[TestWith(['delete'])]
    public function test_catalog_change_during_recommendation_request_returns_422_without_stale_results(string $operation): void
    {
        $this->createCatalog();
        $this->createCachedRecommendations();
        config(['services.llm.driver' => 'openai', 'services.llm.openai.key' => 'test-key', 'services.llm.openai.base_url' => 'https://openai.test/v1']);
        Http::preventStrayRequests();
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) use ($operation) {
            $context = json_decode($request['messages'][1]['content'], true);
            $rows = array_map(fn (array $candidate): array => [
                'event_id' => $candidate['event_id'], 'factors' => array_keys($candidate['evidence']),
                'rationale' => implode(' ', $candidate['evidence']),
            ], $context['candidates']);
            Recommendation::query()->delete();
            match ($operation) {
                'update' => Event::findOrFail('EV_006')->update(['mandatory' => true]),
                'delete' => Event::findOrFail('EV_006')->delete(),
            };

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['recommendations' => $rows])]]]]);
        }]);

        $this->actingAs(User::factory()->create(['role' => 'hr']))->withSession(['role' => 'hr'])->postJson('/employees/E_TEST/recommendations')
            ->assertUnprocessable()->assertJsonValidationErrors('events');

        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseMissing('events', ['event_id' => 'EV_006', 'mandatory' => false]);
        Http::assertSentCount(1);
    }

    private function createCatalog(): void
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
    }

    private function createCachedRecommendations(): Employee
    {
        $fixture = require base_path('tests/Fixtures/career_quest.php');
        $employee = Employee::create([...$fixture['employee'], 'department' => 'Engineering', 'manager_id' => null, 'hire_date' => '2024-01-01', 'tenure_months' => 33, 'preferred_language' => 'ru']);
        $other = Employee::create([...$employee->toArray(), 'employee_id' => 'E_OTHER', 'full_name' => 'Other Test Employee']);
        $employee->recommendations()->create(['event_id' => 'EV_006', 'rank' => 1, 'score' => 9, 'factors' => ['skill_gap'], 'rationale' => 'Cached first choice', 'source' => 'fallback']);
        $other->recommendations()->create(['event_id' => 'EV_SQL', 'rank' => 1, 'score' => 8, 'factors' => ['skill_gap'], 'rationale' => 'Cached other choice', 'source' => 'fallback']);

        return $employee;
    }

    /**
     * @return array{title: string, description: string, type: string, format: string, duration_hours: string, target_roles: list<string>, target_grades: list<string>, develops_skills: list<array{skill_id: string, gain: string, max_level: string}>, prerequisite_skills: list<array{skill_id: string, min_level: string}>, upcoming_sessions: list<string>}
     */
    private function validPayload(): array
    {
        return [
            'title' => 'Architecture Practice',
            'description' => 'Practice designing reliable services.',
            'type' => 'workshop',
            'format' => 'online',
            'duration_hours' => '2.5',
            'target_roles' => ['Backend Engineer'],
            'target_grades' => ['Middle', 'Senior'],
            'develops_skills' => [['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => '2', 'max_level' => '5']],
            'prerequisite_skills' => [['skill_id' => 'SK_SQL', 'min_level' => '0']],
            'upcoming_sessions' => ['2026-12-20', '2026-11-03'],
        ];
    }
}
