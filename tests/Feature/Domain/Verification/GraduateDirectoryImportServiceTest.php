<?php

namespace Tests\Feature\Domain\Verification;

use App\Domain\Verification\Services\GraduateDirectoryImportService;
use App\Models\GraduateDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraduateDirectoryImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_valid_rows_and_reports_skipped_ones_with_errors(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graduates').'.csv';
        file_put_contents($path, implode("\n", [
            'last_name,first_name,phone',
            'Petrov,Ivan,+7 (900) 123-45-67',
            ',Maria,89001112233',
            'Sidorova,Anna,12345',
            'Kuznetsov,Petr,89003334455',
            '',
        ]));

        $result = (new GraduateDirectoryImportService())->import($path);

        unlink($path);

        $this->assertSame(2, $result['imported']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('строка 3', $result['errors'][0]);
        $this->assertStringContainsString('строка 4', $result['errors'][1]);

        $this->assertDatabaseCount('graduate_directory', 2);
        $this->assertDatabaseHas('graduate_directory', [
            'last_name' => 'Petrov',
            'first_name' => 'Ivan',
            'phone' => '+7 (900) 123-45-67',
        ]);

        $graduate = GraduateDirectory::query()->where('last_name', 'Petrov')->firstOrFail();
        $this->assertNotNull($graduate->imported_at);
    }

    public function test_it_reports_missing_required_headers_without_importing_anything(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graduates').'.csv';
        file_put_contents($path, "surname,name\nPetrov,Ivan\n");

        $result = (new GraduateDirectoryImportService())->import($path);

        unlink($path);

        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('last_name', $result['errors'][0]);
        $this->assertDatabaseCount('graduate_directory', 0);
    }

    public function test_clear_deletes_every_row_and_returns_the_deleted_count(): void
    {
        GraduateDirectory::query()->insert([
            ['last_name' => 'Petrov', 'first_name' => 'Ivan', 'phone' => '+79001234567', 'imported_at' => now()],
            ['last_name' => 'Sidorova', 'first_name' => 'Maria', 'phone' => '+79007654321', 'imported_at' => now()],
        ]);

        $deleted = (new GraduateDirectoryImportService())->clear();

        $this->assertSame(2, $deleted);
        $this->assertDatabaseCount('graduate_directory', 0);
    }
}
