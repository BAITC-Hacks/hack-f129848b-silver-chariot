<?php

namespace App\Console\Commands;

use App\Services\LlmService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

#[Signature('ai:recommend {employee : Employee ID} {--dataset=docs/case_1/career_quest_dataset : Dataset directory} {--offline : Use the deterministic fallback without API calls}')]
#[Description('Recommend development activities from a dataset without a database')]
class RecommendEmployee extends Command
{
    public function handle(LlmService $service): int
    {
        $directory = (string) $this->option('dataset');
        if (! is_dir($directory)) {
            $directory = base_path($directory);
        }
        foreach (['employees.json', 'events.json', 'skills.json', 'activity_history.csv'] as $file) {
            if (! is_readable($directory.'/'.$file)) {
                $this->error('Dataset file is missing or unreadable: '.$file);

                return self::FAILURE;
            }
        }

        $originalDriver = config('services.llm.driver');
        try {
            $employees = json_decode(file_get_contents($directory.'/employees.json'), true, 64, JSON_THROW_ON_ERROR);
            $events = json_decode(file_get_contents($directory.'/events.json'), true, 64, JSON_THROW_ON_ERROR);
            $skills = json_decode(file_get_contents($directory.'/skills.json'), true, 64, JSON_THROW_ON_ERROR);
            $employee = collect($employees['employees'])->firstWhere('employee_id', $this->argument('employee'));
            if ($employee === null) {
                $this->error('Employee not found in the dataset.');

                return self::FAILURE;
            }
            $history = [];
            $handle = fopen($directory.'/activity_history.csv', 'r');
            try {
                $header = fgetcsv($handle, escape: '');
                while (($row = fgetcsv($handle, escape: '')) !== false) {
                    if ($row === [null]) {
                        continue;
                    }
                    if (! is_array($header) || count($row) !== count($header)) {
                        throw new InvalidArgumentException('Invalid history CSV row.');
                    }
                    $history[] = array_combine($header, $row);
                }
            } finally {
                fclose($handle);
            }
            if ($this->option('offline')) {
                config(['services.llm.driver' => 'disabled']);
            }
            $result = $service->recommend($employee, $events['events'], $skills['role_profiles'], $history, $skills['skills'], $employees['meta']['as_of_date']);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (JsonException|InvalidArgumentException) {
            $this->error('Invalid dataset JSON, CSV, role profile or snapshot date.');

            return self::FAILURE;
        } finally {
            config(['services.llm.driver' => $originalDriver]);
        }
    }
}
