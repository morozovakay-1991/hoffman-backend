<?php

namespace App\Console\Commands;

use App\Domain\Verification\Services\GraduateDirectoryImportService;
use Illuminate\Console\Command;

class ImportGraduatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'graduates:import {path : Path to the CSV file (columns: last_name,first_name,phone)}';

    /**
     * @var string
     */
    protected $description = 'Import the graduate directory from a CSV file';

    public function handle(GraduateDirectoryImportService $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        $result = $importer->import($path);

        $message = "Imported {$result['imported']} graduate(s).";

        if ($result['errors'] !== []) {
            $message .= ' Skipped '.count($result['errors']).' invalid row(s).';
        }

        $this->info($message);

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
