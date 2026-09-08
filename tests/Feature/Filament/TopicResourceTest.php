<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TopicResource\Pages\ListTopics;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TopicResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/topics')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/topics')->assertForbidden();
    }

    public function test_an_admin_can_access_and_search_topics(): void
    {
        $admin = User::factory()->admin()->create();
        $stress = Topic::factory()->create(['title' => ['ru' => 'Управление стрессом']]);
        $sleep = Topic::factory()->create(['title' => ['ru' => 'Здоровый сон']]);

        $this->actingAs($admin)->get('/backend/topics')->assertSuccessful();

        Livewire::test(ListTopics::class)
            ->assertCanSeeTableRecords([$stress, $sleep])
            ->searchTable('стресс')
            ->assertCanSeeTableRecords([$stress])
            ->assertCanNotSeeTableRecords([$sleep]);
    }

    public function test_a_super_admin_can_access_topics(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/backend/topics')->assertSuccessful();
    }
}
