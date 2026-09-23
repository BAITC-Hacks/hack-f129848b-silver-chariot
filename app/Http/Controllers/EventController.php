<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEventRequest;
use App\Models\Event;
use App\Models\Recommendation;
use App\Models\RoleProfile;
use App\Models\Skill;
use App\Services\RecommendationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:200',
            'format' => ['nullable', Rule::in(array_keys(Event::FORMATS))],
            'type' => ['nullable', Rule::in(array_keys(Event::TYPES))],
        ]);
        $query = Event::withCount('activityRecords');
        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('event_id', 'like', '%'.$search.'%');
            });
        }
        foreach (['format', 'type'] as $field) {
            if ($value = $filters[$field] ?? null) {
                $query->where($field, $value);
            }
        }

        return view('events.index', [
            'events' => $query->orderBy('title')->orderBy('event_id')->paginate(20)->withQueryString(),
            'formats' => Event::FORMATS, 'types' => Event::TYPES,
            'asOf' => RecommendationEngine::SNAPSHOT_DATE,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Event([
            'format' => 'online', 'type' => 'course', 'mandatory' => false,
            'target_roles' => [], 'target_grades' => [], 'develops_skills' => [],
            'prerequisites' => [], 'upcoming_sessions' => [],
        ]));
    }

    public function store(SaveEventRequest $request): RedirectResponse
    {
        $data = $request->eventData();
        $event = DB::transaction(function () use ($data): Event {
            $event = Event::create(['event_id' => 'EV_'.Str::ulid(), ...$data]);
            Recommendation::query()->delete();

            return $event;
        });

        return redirect()->route('hr.events.edit', $event)->with('status', 'Активность создана.');
    }

    public function edit(Event $event): View
    {
        return $this->form($event);
    }

    public function update(SaveEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->eventData();
        DB::transaction(function () use ($event, $data): void {
            $event = Event::whereKey($event->event_id)->lockForUpdate()->firstOrFail();
            if ($event->activityRecords()->exists()
                && array_column($event->develops_skills, null, 'skill_id') != array_column($data['develops_skills'], null, 'skill_id')) {
                throw ValidationException::withMessages([
                    'develops_skills' => 'Нельзя менять прирост навыков у активности с историей участия: это изменит прогресс сотрудников. Создайте новую активность.',
                ]);
            }
            $event->update($data);
            Recommendation::query()->delete();
        });

        return redirect()->route('hr.events.edit', $event)->with('status', 'Активность сохранена.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        DB::transaction(function () use ($event): void {
            $event = Event::whereKey($event->event_id)->lockForUpdate()->firstOrFail();
            if ($event->activityRecords()->exists()) {
                throw ValidationException::withMessages(['event' => 'Нельзя удалить активность с историей участия сотрудников.']);
            }
            Recommendation::query()->delete();
            $event->delete();
        });

        return redirect()->route('hr.events.index')->with('status', 'Активность удалена.');
    }

    private function form(Event $event): View
    {
        return view('events.form', [
            'event' => $event,
            'roles' => RoleProfile::select('role')->distinct()->orderBy('role')->pluck('role'),
            'grades' => ['Junior', 'Middle', 'Senior', 'Lead'],
            'skills' => Skill::orderBy('name')->get(),
            'formats' => Event::FORMATS, 'types' => Event::TYPES,
            'hasHistory' => $event->exists && $event->activityRecords()->exists(),
        ]);
    }
}
