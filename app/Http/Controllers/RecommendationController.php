<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function store(Employee $employee): JsonResponse
    {
        return response()->json(['recommendations' => []], 501);
    }
}
