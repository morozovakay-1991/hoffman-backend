<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ToolResource\Pages\EditTool;
use App\Filament\Resources\ToolResource\Pages\ListTools;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ToolResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/tools')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/tools')->assertForbidden();
    }

    public function test_an_admin_can_access_and_search_tools(): void
    {
        $admin = User::factory()->admin()->create();
        $breathing = Tool::factory()->create(['title' => ['ru' => 'Дыхательная техника']]);
        $journaling = Tool::factory()->create(['title' => ['ru' => 'Ведение дневника']]);

        $this->actingAs($admin)->get('/backend/tools')->assertSuccessful();

        Livewire::test(ListTools::class)
            ->assertCanSeeTableRecords([$breathing, $journaling])
            ->searchTable('Дыхательная')
            ->assertCanSeeTableRecords([$breathing])
            ->assertCanNotSeeTableRecords([$journaling]);
    }

    public function test_a_super_admin_can_access_tools(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/backend/tools')->assertSuccessful();
    }

    public function test_an_admin_can_set_the_sort_order(): void
    {
        $admin = User::factory()->admin()->create();
        $tool = Tool::factory()->create(['sort_order' => 0]);

        $this->actingAs($admin);

        Livewire::test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['sort_order' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $tool->refresh()->sort_order);
    }

    public function test_an_admin_can_drag_and_drop_reorder_the_tools_list(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Tool::factory()->create(['sort_order' => 0]);
        $second = Tool::factory()->create(['sort_order' => 10]);

        $this->actingAs($admin);

        Livewire::test(ListTools::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertTrue($second->refresh()->sort_order < $first->refresh()->sort_order);
    }

    public function test_an_admin_can_set_the_stage_tag(): void
    {
        $admin = User::factory()->admin()->create();
        $tool = Tool::factory()->create(['stage_tag' => null]);

        $this->actingAs($admin);

        Livewire::test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['stage_tag' => 'stage-3'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('stage-3', $tool->refresh()->stage_tag);
    }

    public function test_an_admin_can_filter_tools_by_stage_tag(): void
    {
        $admin = User::factory()->admin()->create();
        $breathing = Tool::factory()->create(['stage_tag' => 'stage-1']);
        $journaling = Tool::factory()->create(['stage_tag' => 'stage-2']);

        $this->actingAs($admin);

        Livewire::test(ListTools::class)
            ->assertCanSeeTableRecords([$breathing, $journaling])
            ->filterTable('stage_tag', ['stage_tag' => 'stage-1'])
            ->assertCanSeeTableRecords([$breathing])
            ->assertCanNotSeeTableRecords([$journaling]);
    }

    public function test_an_admin_can_upload_a_cover_image(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->admin()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditTool::class, ['record' => $tool->getRouteKey()])
            ->fillForm(['cover_image_path' => UploadedFile::fake()->image('cover.jpg')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $tool->refresh()->cover_image_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('tools/', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_an_admin_can_replace_the_cover_image(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->admin()->create();
        $tool = Tool::factory()->create(['cover_image_path' => 'tools/old-cover.jpg']);
        Storage::disk('s3')->put('tools/old-cover.jpg', 'old-cover-content');

        $this->actingAs($admin);

        Livewire::test(EditTool::class, ['record' => $tool->getRouteKey()])
            // As in the UI: remove the current cover first, then upload a new one.
            ->fillForm(['cover_image_path' => []])
            ->fillForm(['cover_image_path' => UploadedFile::fake()->image('new-cover.jpg')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $tool->refresh()->cover_image_path;

        $this->assertNotSame('tools/old-cover.jpg', $path);
        $this->assertStringStartsWith('tools/', $path);
        Storage::disk('s3')->assertExists($path);
    }
}
