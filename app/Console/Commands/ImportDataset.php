<?php

namespace App\Console\Commands;

use App\Services\DatasetImporter;
use Illuminate\Console\Command;

class ImportDataset extends Command
{
    protected $signature = 'data:import {--path=docs/case_1/career_quest_dataset : Dataset directory or individual dataset file}';

    protected $description = 'Merge Career Quest dataset records by primary key';

    public function handle(DatasetImporter $importer): int
    {
        try {
            $counts = $importer->import($this->option('path'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        foreach ($counts as $table => $count) {
            $this->info("{$table}: {$count}");
        }

        return self::SUCCESS;
    }
}
