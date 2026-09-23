<?php

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LlmServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.llm.driver' => 'openai', 'services.llm.timeout' => 8,
            'services.llm.openai.key' => 'test-key',
            'services.llm.openai.base_url' => 'https://openai.test/v1',
            'services.llm.openai.structured_outputs' => true,
        ]);
    }

    public function test_openai_returns_grounded_russian_recommendations_in_the_api_contract(): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) {
            return Http::response($this->openAiResponse($this->answer($request)));
        }]);

        $result = $this->recommend($this->fixture());

        $row = $result['recommendations'][0];
        $this->assertSame(['event_id', 'rank', 'score', 'title', 'type', 'rationale', 'factors', 'source'], array_keys($row));
        $this->assertSame(['EV_006', 1, 'llm'], [$row['event_id'], $row['rank'], $row['source']]);
        $this->assertStringContainsString('System Design — 2 при требуемых 4 для Senior', $row['rationale']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['response_format']['json_schema']['strict'] === true
            && ! str_contains($request->body(), 'Private Test Name')
            && ! str_contains($request->body(), 'E_TEST'));
    }

    public function test_prompt_contains_only_the_top_eight_candidates(): void
    {
        $data = $this->fixture();
        $event = $data['events'][0];
        $data['events'] = [];
        for ($i = 0; $i < 12; $i++) {
            $data['events'][] = array_replace($event, ['event_id' => sprintf('EV_%03d', $i)]);
        }
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) {
            return Http::response($this->openAiResponse($this->answer($request)));
        }]);

        $this->recommend($data);

        Http::assertSent(function (Request $request): bool {
            $context = json_decode($request['messages'][1]['content'], true);

            return count($context['candidates']) === 8
                && $context['candidates'][7]['event_id'] === 'EV_007'
                && isset($context['profile'], $context['gaps'], $context['history']);
        });
    }

    public function test_valid_three_event_response_keeps_unique_ids_and_sequential_ranks(): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) {
            $context = json_decode($request['messages'][1]['content'], true);
            $rows = array_map(fn (array $candidate): array => [
                'event_id' => $candidate['event_id'], 'rationale' => implode(' ', $candidate['evidence']),
                'factors' => array_keys($candidate['evidence']),
            ], $context['candidates']);

            return Http::response($this->openAiResponse(['recommendations' => $rows]));
        }]);

        $result = $this->recommend($this->fixture());

        $this->assertSame(['EV_006', 'EV_SQL', 'EV_036'], array_column($result['recommendations'], 'event_id'));
        $this->assertSame([1, 2, 3], array_column($result['recommendations'], 'rank'));
        $this->assertSame(['llm', 'llm', 'llm'], array_column($result['recommendations'], 'source'));
        Http::assertSentCount(1);
    }

    public function test_anthropic_truncation_falls_back_instead_of_trusting_partial_tool_input(): void
    {
        config(['services.llm.driver' => 'anthropic', 'services.llm.anthropic.key' => 'test', 'services.llm.anthropic.base_url' => 'https://anthropic.test']);
        Http::fake(['https://anthropic.test/v1/messages' => Http::response(['stop_reason' => 'max_tokens', 'content' => []])]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('fallback', $result['recommendations'][0]['source']);
        Http::assertSentCount(1);
    }

    public function test_compatible_openai_endpoint_can_use_json_mode(): void
    {
        config(['services.llm.openai.structured_outputs' => false]);
        Http::fake(['https://openai.test/v1/chat/completions' => fn (Request $request) => Http::response($this->openAiResponse($this->answer($request)))]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('llm', $result['recommendations'][0]['source']);
        Http::assertSent(fn (Request $request): bool => $request['response_format'] === ['type' => 'json_object']);
    }

    public static function anthropicUrls(): array
    {
        return ['root' => ['https://anthropic.test'], 'v1 suffix' => ['https://anthropic.test/v1/']];
    }

    #[DataProvider('anthropicUrls')]
    public function test_anthropic_uses_messages_and_validates_tool_input(string $baseUrl): void
    {
        config(['services.llm.driver' => 'anthropic', 'services.llm.anthropic.key' => 'anthropic-test', 'services.llm.anthropic.base_url' => $baseUrl]);
        Http::fake(['https://anthropic.test/v1/messages' => function (Request $request) {
            return Http::response(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'name' => 'recommend', 'input' => $this->answer($request, true)]]]);
        }]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('llm', $result['recommendations'][0]['source']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'anthropic-test')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['tool_choice']['name'] === 'recommend');
    }

    public static function invalidAnswers(): array
    {
        return array_combine(
            ['unknown event', 'duplicate events', 'empty list', 'too many', 'two factors', 'unknown factor', 'duplicate factor', 'fabricated rationale', 'missing history', 'missing critical', 'wrong priority', 'extra field', 'not a list'],
            array_map(fn (string $case): array => [$case], ['unknown event', 'duplicate events', 'empty list', 'too many', 'two factors', 'unknown factor', 'duplicate factor', 'fabricated rationale', 'missing history', 'missing critical', 'wrong priority', 'extra field', 'not a list']),
        );
    }

    #[DataProvider('invalidAnswers')]
    public function test_invalid_model_answers_fall_back_as_a_whole(string $case): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request) use ($case) {
            $answer = $this->answer($request);
            $row = $answer['recommendations'][0];
            $answer = match ($case) {
                'unknown event' => ['recommendations' => [array_replace($row, ['event_id' => 'EV_UNKNOWN'])]],
                'duplicate events' => ['recommendations' => [$row, $row]],
                'empty list' => ['recommendations' => []],
                'too many' => ['recommendations' => [$row, $row, $row, $row]],
                'two factors' => ['recommendations' => [array_replace($row, ['factors' => ['grade_requirement', 'skill_gap']])]],
                'unknown factor' => ['recommendations' => [array_replace($row, ['factors' => ['grade_requirement', 'skill_gap', 'invented']])]],
                'duplicate factor' => ['recommendations' => [array_replace($row, ['factors' => ['grade_requirement', 'skill_gap', 'skill_gap']])]],
                'fabricated rationale' => ['recommendations' => [array_replace($row, ['rationale' => $row['rationale'].' Гарантированное повышение через 2 дня.'])]],
                'missing history' => ['recommendations' => [array_replace($row, ['factors' => ['grade_requirement', 'skill_gap', 'critical_skill']])]],
                'missing critical' => ['recommendations' => [array_replace($row, ['factors' => ['grade_requirement', 'skill_gap', 'history_clean']])]],
                'wrong priority' => ['recommendations' => [array_replace($row, ['event_id' => 'EV_SQL'])]],
                'extra field' => ['recommendations' => [array_merge($row, ['score' => 999])]],
                'not a list' => ['recommendations' => ['row' => $row]],
            };

            return Http::response($this->openAiResponse($answer));
        }]);

        $result = $this->recommend($this->fixture());

        $this->assertSame(['EV_006', 'EV_SQL', 'EV_036'], array_column($result['recommendations'], 'event_id'));
        $this->assertSame(['fallback', 'fallback', 'fallback'], array_column($result['recommendations'], 'source'));
        Http::assertSentCount(1);
    }

    public static function failedResponses(): array
    {
        return [
            'invalid json' => [200, 'not json'],
            'invalid inner json' => [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '```json {} ```']]]]],
            'refusal' => [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'No.', 'content' => '{}']]]]],
            'truncated' => [200, ['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{}']]]]],
            'unauthorized' => [401, ['error' => ['message' => 'upstream-secret']]],
            'rate limit' => [429, ['error' => ['message' => 'upstream-secret']]],
            'server error' => [500, ['error' => ['message' => 'upstream-secret']]],
            'redirect' => [302, 'redirect'],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_provider_errors_return_fallback_without_leaking_the_response(int $status, array|string $body): void
    {
        Log::spy();
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response($body, $status)]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('fallback', $result['recommendations'][0]['source']);
        $this->assertStringNotContainsString('upstream-secret', json_encode($result));
        Log::shouldHaveReceived('notice')->with('Career Quest used deterministic recommendations.', ['reason' => 'invalid_response'])->once();
        Log::shouldNotHaveReceived('notice', ['upstream-secret']);
        Http::assertSentCount(1);
    }

    public function test_connection_timeout_returns_fallback_without_retrying(): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => Http::failedConnection('timeout')]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('fallback', $result['recommendations'][0]['source']);
        Http::assertSentCount(1);
    }

    public static function unavailableDrivers(): array
    {
        return ['disabled' => ['disabled', 'test-key'], 'unknown' => ['unknown', 'test-key'], 'missing key' => ['openai', '']];
    }

    #[DataProvider('unavailableDrivers')]
    public function test_unavailable_driver_uses_offline_fallback(string $driver, string $key): void
    {
        config(['services.llm.driver' => $driver, 'services.llm.openai.key' => $key]);
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response()]);

        $result = $this->recommend($this->fixture());

        $this->assertSame('fallback', $result['recommendations'][0]['source']);
        $this->assertGreaterThanOrEqual(3, count($result['recommendations'][0]['factors']));
        Http::assertNothingSent();
    }

    public function test_no_candidates_returns_empty_list_without_calling_a_provider(): void
    {
        $data = $this->fixture();
        $data['events'] = [];
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response()]);

        $result = $this->recommend($data);

        $this->assertSame(['recommendations' => []], $result);
        Http::assertNothingSent();
    }

    public function test_request_timeout_is_capped_and_redirects_are_disabled(): void
    {
        config(['services.llm.timeout' => 60]);
        $seenOptions = [];
        Http::fake(['https://openai.test/v1/chat/completions' => function (Request $request, array $options) use (&$seenOptions) {
            $seenOptions = $options;

            return Http::response($this->openAiResponse($this->answer($request)));
        }]);

        $this->recommend($this->fixture());

        $this->assertSame(8.0, $seenOptions['timeout']);
        $this->assertSame(3.0, $seenOptions['connect_timeout']);
        $this->assertFalse($seenOptions['allow_redirects']);
        Http::assertSentCount(1);
    }

    private function fixture(): array
    {
        return require base_path('tests/Fixtures/career_quest.php');
    }

    private function recommend(array $data): array
    {
        return app(LlmService::class)->recommend($data['employee'], $data['events'], $data['role_profiles'], $data['history'], $data['skills']);
    }

    private function answer(Request $request, bool $anthropic = false): array
    {
        $context = json_decode($request['messages'][$anthropic ? 0 : 1]['content'], true);
        $candidate = $context['candidates'][0];

        return ['recommendations' => [[
            'event_id' => $candidate['event_id'],
            'factors' => array_keys($candidate['evidence']),
            'rationale' => implode(' ', $candidate['evidence']),
        ]]];
    }

    private function openAiResponse(array $answer): array
    {
        return ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($answer, JSON_UNESCAPED_UNICODE)]]]];
    }
}
