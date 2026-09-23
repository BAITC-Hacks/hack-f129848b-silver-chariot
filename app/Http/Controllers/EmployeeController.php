<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Event;
use App\Models\Skill;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $request->validate(['search' => 'nullable|string|max:200']);
        $query = Employee::query();
        if ($request->session()->get('role', 'employee') !== 'hr') {
            $query->whereKey($request->session()->get('employee_id', 'E0001'));
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('full_name', 'like', '%'.$search.'%')->orWhere('role', 'like', '%'.$search.'%')->orWhere('grade', 'like', '%'.$search.'%');
            });
        }
        $employees = $query->orderBy('employee_id')->paginate(25)->withQueryString();

        return $request->expectsJson() ? response()->json(['employees' => $employees]) : view('employees.index', compact('employees'));
    }

    public function show(Request $request, Employee $employee, ProgressService $progress): View|JsonResponse
    {
        $data = ['employee' => $employee, 'gaps' => $progress->gaps($employee), 'grade_readiness' => $progress->gradeReadiness($employee), 'history' => $employee->activityRecords()->with('event')->orderByDesc('date')->get(), 'skills' => Skill::all()->keyBy('skill_id'), 'events' => Event::orderBy('event_id')->get()];
        $employee->setAttribute('skills', $progress->currentSkills($employee));

        return $request->expectsJson() ? response()->json($data) : view('employees.show', $data);
    }
}
