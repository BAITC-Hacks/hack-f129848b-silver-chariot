<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Event;
use App\Models\Skill;
use App\Services\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'role' => 'nullable|string|max:200',
            'grade' => 'nullable|string|in:Junior,Middle,Senior,Lead',
        ]);

        if (! $request->expectsJson() && $request->session()->get('role', 'employee') !== 'hr') {
            return redirect()->route('employees.show', $request->user()->employee_id);
        }

        $query = Employee::query();
        if (! $request->user()->can('access-hr')) {
            $query->whereKey($request->user()->employee_id);
        }
        $roles = (clone $query)->select('role')->distinct()->orderBy('role')->pluck('role');
        $grades = ['Junior', 'Middle', 'Senior', 'Lead'];

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('full_name', 'like', '%'.$search.'%')->orWhere('role', 'like', '%'.$search.'%')->orWhere('grade', 'like', '%'.$search.'%');
            });
        }
        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }
        if ($grade = $request->string('grade')->toString()) {
            $query->where('grade', $grade);
        }
        $employees = $query->orderBy('employee_id')->paginate(25)->withQueryString();

        return $request->expectsJson() ? response()->json(['employees' => $employees]) : view('employees.index', compact('employees', 'roles', 'grades'));
    }

    public function show(Request $request, Employee $employee, ProgressService $progress): View|JsonResponse
    {
        $history = $employee->activityRecords()->with('event')->orderByDesc('date')->orderByDesc('record_id')->get();
        $completedEventIds = $history->where('status', 'completed')->pluck('event_id')->reject(fn (string $id): bool => $id === 'EV_036');
        $data = [
            'employee' => $employee,
            'gaps' => $progress->gaps($employee),
            'grade_readiness' => $progress->gradeReadiness($employee),
            'history' => $history,
            'skills' => Skill::all()->keyBy('skill_id'),
            'events' => Event::whereNotIn('event_id', $completedEventIds)->orderBy('event_id')->get(),
            'recommendations' => $employee->recommendations()->with('event')->orderBy('rank')->get(),
        ];
        $employee->setAttribute('skills', $progress->currentSkills($employee));

        return $request->expectsJson() ? response()->json($data) : view('employees.show', $data);
    }
}
