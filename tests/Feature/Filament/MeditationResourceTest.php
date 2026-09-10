<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\MeditationResource\Pages\EditMeditation;
use App\Filament\Resources\MeditationResource\Pages\ListMeditations;
use App\Models\Meditation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MeditationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/meditations')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/meditations')->assertForbidden();
    }

    public function test_an_admin_can_access_and_search_meditations(): void
    {
        $admin = User::factory()->admin()->create();
        $morning = Meditation::factory()->create(['title' => ['ru' => 'Утренняя медитация']]);
        $evening = Meditation::factory()->create(['title' => ['ru' => 'Вечерняя медитация']]);

        $this->actingAs($admin)->get('/backend/meditations')->assertSuccessful();

        Livewire::test(ListMeditations::class)
            ->assertCanSeeTableRecords([$morning, $evening])
            ->searchTable('Утренняя')
            ->assertCanSeeTableRecords([$morning])
            ->assertCanNotSeeTableRecords([$evening]);
    }

    public function test_a_super_admin_can_access_meditations(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/backend/meditations')->assertSuccessful();
    }

    public function test_an_admin_can_set_the_sort_order(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->admin()->create();
        $meditation = Meditation::factory()->create([
            'sort_order' => 0,
            'audio_path' => 'meditations/sort-order-test.mp3',
        ]);
        Storage::disk('s3')->put($meditation->audio_path, 'fake-audio-content');

        $this->actingAs($admin);

        Livewire::test(EditMeditation::class, ['record' => $meditation->getRouteKey()])
            ->fillForm(['sort_order' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $meditation->refresh()->sort_order);
    }

    public function test_an_admin_can_drag_and_drop_reorder_the_meditations_list(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Meditation::factory()->create(['sort_order' => 0]);
        $second = Meditation::factory()->create(['sort_order' => 10]);

        $this->actingAs($admin);

        Livewire::test(ListMeditations::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertTrue($second->refresh()->sort_order < $first->refresh()->sort_order);
    }
}
