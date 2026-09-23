<?php

namespace App\Http\Controllers;

use App\Services\HrAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request, HrAnalyticsService $analytics): View|JsonResponse
    {
        $data = $analytics->dashboard();

        return $request->expectsJson() ? response()->json($data) : view('hr.index', $data);
    }
}
