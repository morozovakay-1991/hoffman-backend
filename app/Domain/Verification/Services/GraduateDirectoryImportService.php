<?php

namespace App\Domain\Verification\Services;

use App\Models\GraduateDirectory;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Imports and clears the graduate directory from a CSV file (columns: last_name, first_name, phone).
 */
class GraduateDirectoryImportService
{
    private const REQUIRED_HEADERS = ['last_name', 'first_name', 'phone'];

    private const INSERT_CHUNK_SIZE = 500;

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $reader = SimpleExcelReader::create($path, 'csv')
            ->trimHeaderRow()
            ->formatHeadersUsing(fn (string $header): string => mb_strtolower(trim($header)));

        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $reader->getHeaders() ?? []);

        if ($missingHeaders !== []) {
            return [
                'imported' => 0,
                'errors' => ['в файле отсутствуют колонки: '.implode(', ', $missingHeaders)],
            ];
        }

        $importedAt = Carbon::now();
        $imported = 0;
        $errors = [];
        $batch = [];
        $rowNumber = 1;

        foreach ($reader->getRows() as $row) {
            $rowNumber++;

            $data = [
                'last_name' => trim((string) ($row['last_name'] ?? '')),
                'first_name' => trim((string) ($row['first_name'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
            ];

            if ($data['last_name'] === '' && $data['first_name'] === '' && $data['phone'] === '') {
                continue;
            }

            $validator = Validator::make($data, $this->rules());

            if ($validator->fails()) {
                $errors[] = "строка {$rowNumber}: {$validator->errors()->first()}";

                continue;
            }

            $batch[] = [...$data, 'imported_at' => $importedAt];
            $imported++;

            if (count($batch) >= self::INSERT_CHUNK_SIZE) {
                GraduateDirectory::query()->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            GraduateDirectory::query()->insert($batch);
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * Delete every row in the directory and return how many were removed.
     */
    public function clear(): int
    {
        return GraduateDirectory::query()->delete();
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    private function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen(preg_replace('/\D+/', '', (string) $value) ?? '') < 10) {
                        $fail('некорректный номер телефона');
                    }
                },
            ],
        ];
    }
}
