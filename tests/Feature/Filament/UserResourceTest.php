<?php

namespace Tests\Feature\Filament;

use App\Enums\GraduateStatus;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/users')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/users')->assertForbidden();
    }

    public function test_an_admin_can_search_users_by_name_or_email(): void
    {
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $john = User::factory()->create(['name' => 'John Smith', 'email' => 'john@example.com']);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $jane, $john])
            ->searchTable('jane@example.com')
            ->assertCanSeeTableRecords([$jane])
            ->assertCanNotSeeTableRecords([$john]);
    }

    public function test_an_admin_can_filter_users_by_graduate_status(): void
    {
        $admin = User::factory()->admin()->create();
        $confirmed = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        $unverified = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->filterTable('graduate_status', GraduateStatus::Confirmed->value)
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$unverified]);
    }

    public function test_an_admin_can_block_and_unblock_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('block', $target)
            ->assertNotified();

        $this->assertNotNull($target->fresh()->blocked_at);

        Livewire::test(ListUsers::class)
            ->callTableAction('unblock', $target->refresh())
            ->assertNotified();

        $this->assertNull($target->fresh()->blocked_at);
    }
}
