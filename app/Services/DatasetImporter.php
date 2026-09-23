<?php

namespace App\Services;

use App\Models\ActivityRecord;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Recommendation;
use App\Models\RoleProfile;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DatasetImporter
{
    public function import(string $path): array
    {
        $files = [];
        foreach (['skills.json', 'employees.json', 'events.json', 'activity_history.csv'] as $name) {
            if (is_file($path.'/'.$name)) {
                $files[$name] = $path.'/'.$name;
            }
        }
        if (is_file($path)) {
            $files = [basename($path) => $path];
        }

        return $this->importFiles($files);
    }

    public function importFiles(array $files): array
    {
        if (! $files || array_diff(array_keys($files), ['skills.json', 'employees.json', 'events.json', 'activity_history.csv'])) {
            throw ValidationException::withMessages(['files' => 'Provide supported dataset files.']);
        }

        return DB::transaction(function () use ($files): array {
            $counts = [];
            if (isset($files['skills.json'])) {
                $data = $this->json($files['skills.json']);
                Validator::make($data, ['skills' => 'present|array', 'role_profiles' => 'present|array'])->validate();
                $counts['skills'] = $this->rows(Skill::class, $data['skills'] ?? [], ['skill_id']);
                $counts['role_profiles'] = $this->rows(RoleProfile::class, $data['role_profiles'] ?? [], ['role', 'grade']);
                Recommendation::query()->delete();
            }
            if (isset($files['employees.json'])) {
                $data = $this->json($files['employees.json']);
                Validator::make($data, ['employees' => 'present|array', 'employees.*' => 'array', 'employees.*.employee_id' => 'required|string'])->validate();
                $rows = $data['employees'];
                $managers = [];
                foreach ($rows as &$row) {
                    $managers[$row['employee_id'] ?? ''] = $row['manager_id'] ?? null;
                    $row['manager_id'] = null;
                } unset($row);
                $counts['employees'] = $this->rows(Employee::class, $rows, ['employee_id']);
                ActivityRecord::whereIn('employee_id', array_keys($managers))->update(['skills_applied' => false]);
                Recommendation::whereIn('employee_id', array_keys($managers))->delete();
                foreach ($managers as $id => $manager) {
                    Validator::make(['manager_id' => $manager], ['manager_id' => 'nullable|exists:employees,employee_id'])->validate();
                    Employee::whereKey($id)->update(['manager_id' => $manager]);
                }
            }
            if (isset($files['events.json'])) {
                $data = $this->json($files['events.json']);
                Validator::make($data, ['events' => 'present|array'])->validate();
                $counts['events'] = $this->rows(Event::class, $data['events'], ['event_id']);
                Recommendation::query()->delete();
            }
            if (isset($files['activity_history.csv'])) {
                $handle = fopen($files['activity_history.csv'], 'r');
                if (! $handle) {
                    throw ValidationException::withMessages(['files' => 'Cannot read CSV.']);
                }
                try {
                    $header = fgetcsv($handle, 0, ',', '"', '');
                    $expected = (new ActivityRecord)->getFillable();
                    if (! $header || array_diff($expected, $header) || count($header) !== count($expected)) {
                        throw ValidationException::withMessages(['files' => 'Invalid activity CSV header.']);
                    }
                    $rows = [];
                    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                        if ($values === [null]) {
                            continue;
                        }
                        if (count($values) !== count($header)) {
                            throw ValidationException::withMessages(['files' => 'Invalid CSV row.']);
                        }
                        $rows[] = array_map(fn ($value) => $value === '' ? null : $value, array_combine($header, $values));
                    }
                    $affectedEmployeeIds = ActivityRecord::whereIn('record_id', array_column($rows, 'record_id'))->pluck('employee_id')
                        ->merge(array_column($rows, 'employee_id'))->unique();
                    $counts['activity_records'] = $this->rows(ActivityRecord::class, $rows, ['record_id']);
                    Recommendation::whereIn('employee_id', $affectedEmployeeIds)->delete();
                } finally {
                    fclose($handle);
                }
            }

            return $counts;
        });
    }

    private function json(string $path): array
    {
        try {
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['files' => 'Invalid JSON: '.$exception->getMessage()]);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['files' => 'Expected a JSON object.']);
        }

        return $data;
    }

    private function rows(string $model, array $rows, array $keys): int
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['files' => 'Expected an object for each record.']);
            }
            $rules = array_fill_keys((new $model)->getFillable(), 'required');
            foreach (['manager_id', 'career_goal', 'due_date', 'score', 'feedback_rating'] as $field) {
                if (isset($rules[$field])) {
                    $rules[$field] = 'present|nullable';
                }
            }
            foreach ((new $model)->getCasts() as $field => $cast) {
                if ($cast === 'array') {
                    $rules[$field] = ($field === 'career_goal' ? 'present|nullable' : 'present').'|array';
                }
            }
            if ($model === Employee::class) {
                $rules['full_name'] = 'required|string|max:255';
                $rules['tenure_months'] = 'required|integer|min:0';
                $rules['work_format'] = 'required|in:office,hybrid,remote';
                $rules['preferred_language'] = 'required|in:kk,ru,en';
                $rules['career_goal'] .= '|min:1';
                $rules['career_goal.target_role'] = 'required_with:career_goal|string';
                $rules['career_goal.target_grade'] = 'required_with:career_goal|in:Junior,Middle,Senior,Lead';
                $rules['skills.*'] = 'integer|between:0,5';
                $rules['grade'] = 'required|in:Junior,Middle,Senior,Lead';
            }
            if ($model === ActivityRecord::class) {
                $rules['employee_id'] = 'required|exists:employees,employee_id';
                $rules['event_id'] = 'required|exists:events,event_id';
                $rules['status'] = 'required|in:completed,in_progress,dropped,no_show,declined,overdue';
                $rules['completion_pct'] = 'required|integer|between:0,100';
                $rules['score'] = 'nullable|integer|between:0,100';
                $rules['feedback_rating'] = 'nullable|integer|between:1,5';
                $rules['assigned_by'] = 'required|in:self,manager,hr';
            }
            foreach (['date', 'due_date', 'hire_date', 'last_review_date'] as $date) {
                if (isset($rules[$date])) {
                    $rules[$date] .= '|date_format:Y-m-d';
                }
            }
            $valid = Validator::make($row, $rules)->validate();
            if ($model === Employee::class && $valid['career_goal'] !== null) {
                $goal = $valid['career_goal'];
                if (! RoleProfile::where('role', $goal['target_role'])->where('grade', $goal['target_grade'])->exists()) {
                    throw ValidationException::withMessages([
                        'career_goal.target_role' => "Карьерная цель сотрудника {$valid['employee_id']}: указанная пара роли и грейда отсутствует в каталоге.",
                    ]);
                }
            }
            $model::updateOrCreate(array_intersect_key($valid, array_flip($keys)), $valid);
        }

        return count($rows);
    }
}
