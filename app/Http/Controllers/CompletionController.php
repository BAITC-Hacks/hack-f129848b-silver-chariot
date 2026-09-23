<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Event;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompletionController extends Controller
{
    public function store(Request $request, Employee $employee, ProgressService $progress): JsonResponse
    {
        $data = $request->validate(['event_id' => 'required|string|exists:events,event_id']);

        return response()->json($progress->complete($employee, Event::findOrFail($data['event_id'])));
    }
}
