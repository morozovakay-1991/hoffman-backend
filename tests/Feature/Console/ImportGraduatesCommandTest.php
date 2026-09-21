<?php

namespace Tests\Feature\Console;

use App\Models\GraduateDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportGraduatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_graduates_from_a_csv_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graduates').'.csv';
        file_put_contents($path, "last_name,first_name,phone\nPetrov,Ivan,+7 (900) 123-45-67\nSidorova,Maria,89001112233\n");

        Artisan::call('graduates:import', ['path' => $path]);

        unlink($path);

        $this->assertDatabaseCount('graduate_directory', 2);
        $this->assertDatabaseHas('graduate_directory', [
            'last_name' => 'Petrov',
            'first_name' => 'Ivan',
            'phone' => '+7 (900) 123-45-67',
        ]);

        $graduate = GraduateDirectory::query()->where('last_name', 'Petrov')->firstOrFail();
        $this->assertNotNull($graduate->imported_at);
    }

    public function test_it_fails_gracefully_for_a_missing_file(): void
    {
        $exitCode = Artisan::call('graduates:import', ['path' => '/nonexistent/graduates.csv']);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('graduate_directory', 0);
    }

    public function test_it_reports_invalid_rows_without_failing_the_command(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graduates').'.csv';
        file_put_contents($path, implode("\n", [
            'last_name,first_name,phone',
            'Petrov,Ivan,+7 (900) 123-45-67',
            'Sidorova,Anna,12345',
        ]));

        $exitCode = Artisan::call('graduates:import', ['path' => $path]);

        unlink($path);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Imported 1 graduate(s). Skipped 1 invalid row(s).', Artisan::output());
        $this->assertDatabaseCount('graduate_directory', 1);
    }

    public function test_it_reports_missing_required_headers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graduates').'.csv';
        file_put_contents($path, "surname,name\nPetrov,Ivan\n");

        $exitCode = Artisan::call('graduates:import', ['path' => $path]);

        unlink($path);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('graduate_directory', 0);
    }
}
