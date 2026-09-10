<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ToolResource\Pages\EditTool;
use App\Filament\Resources\ToolResource\Pages\ListTools;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
