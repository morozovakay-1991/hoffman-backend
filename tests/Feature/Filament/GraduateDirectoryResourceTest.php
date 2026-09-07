<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GraduateDirectoryResource\Pages\ListGraduateDirectories;
use App\Models\GraduateDirectory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class GraduateDirectoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/graduate-directories')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/graduate-directories')->assertForbidden();
    }

    public function test_an_admin_can_see_and_search_directory_records(): void
    {
        $admin = User::factory()->admin()->create();

        GraduateDirectory::query()->insert([
            ['last_name' => 'Petrov', 'first_name' => 'Ivan', 'phone' => '+79001234567', 'imported_at' => now()],
            ['last_name' => 'Sidorova', 'first_name' => 'Maria', 'phone' => '+79007654321', 'imported_at' => now()],
        ]);

        $this->actingAs($admin);

        Livewire::test(ListGraduateDirectories::class)
            ->assertCanSeeTableRecords(GraduateDirectory::all())
            ->searchTable('Petrov')
            ->assertCanSeeTableRecords(GraduateDirectory::where('last_name', 'Petrov')->get())
            ->assertCanNotSeeTableRecords(GraduateDirectory::where('last_name', 'Sidorova')->get());
    }

    public function test_importing_a_csv_inserts_valid_rows_and_reports_skipped_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $csv = implode("\n", [
            'last_name,first_name,phone',
            'Petrov,Ivan,+7 (900) 123-45-67',
            ',Maria,89001112233',
        ]);
        $file = UploadedFile::fake()->createWithContent('graduates.csv', $csv);

        Livewire::test(ListGraduateDirectories::class)
            ->mountTableAction('import')
            ->setTableActionData(['file' => $file])
            ->callMountedTableAction()
            ->assertNotified();

        $this->assertDatabaseCount('graduate_directory', 1);
        $this->assertDatabaseHas('graduate_directory', [
            'last_name' => 'Petrov',
            'first_name' => 'Ivan',
        ]);
    }

    public function test_clearing_the_directory_deletes_every_record(): void
    {
        $admin = User::factory()->admin()->create();

        GraduateDirectory::query()->insert([
            'last_name' => 'Petrov', 'first_name' => 'Ivan', 'phone' => '+79001234567', 'imported_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ListGraduateDirectories::class)
            ->mountTableAction('clear')
            ->callMountedTableAction()
            ->assertNotified();

        $this->assertDatabaseCount('graduate_directory', 0);
    }
}
