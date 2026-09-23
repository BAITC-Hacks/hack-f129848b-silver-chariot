<?php

namespace App\Http\Controllers;

use App\Services\DatasetImporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function create(): View
    {
        return view('admin.upload');
    }

    public function store(Request $request, DatasetImporter $importer): JsonResponse|RedirectResponse
    {
        $request->validate(['employees' => 'required_without:activity_history|file|max:10240', 'activity_history' => 'required_without:employees|file|max:10240']);
        $files = [];
        foreach (['employees' => 'employees.json', 'activity_history' => 'activity_history.csv'] as $field => $name) {
            if ($request->hasFile($field)) {
                $files[$name] = $request->file($field)->getRealPath();
            }
        }
        try {
            $counts = $importer->importFiles($files);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['files' => 'Dataset references or fields are invalid.']);
        }

        return $request->expectsJson() ? response()->json(['imported' => $counts]) : back()->with('imported', $counts);
    }
}
