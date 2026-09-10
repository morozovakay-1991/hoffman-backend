<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TopicResource\Pages\EditTopic;
use App\Filament\Resources\TopicResource\Pages\ListTopics;
use App\Models\Meditation;
use App\Models\Tool;
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

    public function test_an_admin_can_open_the_create_topic_page(): void
    {
        $admin = User::factory()->admin()->create();
        Tool::factory()->create(['title' => ['ru' => 'Дыхательная техника']]);
        Meditation::factory()->create(['title' => ['ru' => 'Утренняя медитация']]);

        $this->actingAs($admin)->get('/backend/topics/create')->assertSuccessful();
    }

    public function test_an_admin_can_open_the_edit_topic_page_with_related_records(): void
    {
        $admin = User::factory()->admin()->create();
        $topic = Topic::factory()->create();
        $tool = Tool::factory()->create(['title' => ['ru' => 'Дыхательная техника']]);
        $meditation = Meditation::factory()->create(['title' => ['ru' => 'Утренняя медитация']]);
        $topic->tools()->attach($tool);
        $topic->meditations()->attach($meditation);

        $this->actingAs($admin)->get("/backend/topics/{$topic->id}/edit")->assertSuccessful();
    }

    public function test_an_admin_can_set_the_sort_order(): void
    {
        $admin = User::factory()->admin()->create();
        $topic = Topic::factory()->create(['sort_order' => 0]);

        $this->actingAs($admin);

        Livewire::test(EditTopic::class, ['record' => $topic->getRouteKey()])
            ->fillForm(['sort_order' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $topic->refresh()->sort_order);
    }

    public function test_an_admin_can_drag_and_drop_reorder_the_topics_list(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Topic::factory()->create(['sort_order' => 0]);
        $second = Topic::factory()->create(['sort_order' => 10]);

        $this->actingAs($admin);

        Livewire::test(ListTopics::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertTrue($second->refresh()->sort_order < $first->refresh()->sort_order);
    }
}
