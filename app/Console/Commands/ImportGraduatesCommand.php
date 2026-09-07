<?php

namespace App\Console\Commands;

use App\Models\GraduateDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        $importedAt = Carbon::now();
        $imported = 0;
        $skipped = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                $skipped++;

                continue;
            }

            [$lastName, $firstName, $phone] = array_map('trim', array_slice($row, 0, 3));

            if ($lastName === '' || $firstName === '' || $phone === '') {
                $skipped++;

                continue;
            }

            if (strcasecmp($lastName, 'last_name') === 0 && strcasecmp($firstName, 'first_name') === 0) {
                continue;
            }

            $batch[] = [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'phone' => $phone,
                'imported_at' => $importedAt,
            ];
            $imported++;

            if (count($batch) >= 500) {
                GraduateDirectory::query()->insert($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            GraduateDirectory::query()->insert($batch);
        }

        $message = "Imported {$imported} graduate(s).";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
