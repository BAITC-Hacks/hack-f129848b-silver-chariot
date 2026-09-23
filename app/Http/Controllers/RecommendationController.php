<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Event;
use App\Models\RoleProfile;
use App\Models\Skill;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecommendationController extends Controller
{
    public function store(Employee $employee, LlmService $llm): JsonResponse
    {
        $events = Event::orderBy('event_id')->get()->toArray();
        $result = $llm->recommend($employee->toArray(), $events, RoleProfile::all()->toArray(), $employee->activityRecords()->get()->toArray(), Skill::all()->toArray());

        DB::transaction(function () use ($employee, $result, $events): void {
            Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            if (Event::orderBy('event_id')->lockForUpdate()->get()->toArray() !== $events) {
                throw ValidationException::withMessages(['events' => 'Каталог активностей изменился во время подбора. Получите рекомендации ещё раз.']);
            }
            $employee->recommendations()->delete();
            foreach ($result['recommendations'] as $recommendation) {
                $employee->recommendations()->create([
                    ...Arr::only($recommendation, ['event_id', 'rank', 'score', 'factors', 'rationale', 'source']),
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json($result);
    }
}
