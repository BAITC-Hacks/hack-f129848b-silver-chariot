<?php

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendEmployeeCommandTest extends TestCase
{
    public function test_offline_command_outputs_recommendations_without_a_database_or_api(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response()]);

        $exit = Artisan::call('ai:recommend', ['employee' => 'E0028', '--offline' => true]);

        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $exit);
        $this->assertNotEmpty($result['recommendations']);
        $this->assertSame('fallback', $result['recommendations'][0]['source']);
        Http::assertNothingSent();
    }

    public function test_missing_employee_returns_a_readable_error(): void
    {
        $this->artisan('ai:recommend', ['employee' => 'DOES_NOT_EXIST', '--offline' => true])
            ->expectsOutput('Employee not found in the dataset.')
            ->assertFailed();
    }

    public function test_missing_dataset_returns_a_readable_error(): void
    {
        $this->artisan('ai:recommend', ['employee' => 'E0028', '--dataset' => 'missing-directory', '--offline' => true])
            ->expectsOutput('Dataset file is missing or unreadable: employees.json')
            ->assertFailed();
    }

    public function test_all_two_hundred_dataset_profiles_produce_safe_fallback_results(): void
    {
        config(['services.llm.driver' => 'disabled']);
        Http::preventStrayRequests();
        $directory = base_path('docs/case_1/career_quest_dataset');
        $employees = json_decode(file_get_contents($directory.'/employees.json'), true)['employees'];
        $events = json_decode(file_get_contents($directory.'/events.json'), true)['events'];
        $skills = json_decode(file_get_contents($directory.'/skills.json'), true);
        $handle = fopen($directory.'/activity_history.csv', 'r');
        $header = fgetcsv($handle, escape: '');
        $history = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $history[] = array_combine($header, $row);
        }
        fclose($handle);
        $eventsById = array_column($events, null, 'event_id');
        $service = app(LlmService::class);

        foreach ($employees as $employee) {
            $result = $service->recommend($employee, $events, $skills['role_profiles'], $history, $skills['skills']);

            $this->assertLessThanOrEqual(3, count($result['recommendations']), $employee['employee_id']);
            foreach ($result['recommendations'] as $recommendation) {
                $event = $eventsById[$recommendation['event_id']];
                $this->assertFalse($event['mandatory']);
                $this->assertContains($employee['role'], $event['target_roles']);
                $this->assertContains($employee['grade'], $event['target_grades']);
                $this->assertGreaterThanOrEqual(3, count($recommendation['factors']));
                $this->assertSame('fallback', $recommendation['source']);
                $this->assertNotEmpty($recommendation['rationale']);
            }
        }

        $this->assertCount(200, $employees);
    }
}
