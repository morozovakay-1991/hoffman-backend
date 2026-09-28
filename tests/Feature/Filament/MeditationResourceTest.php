<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\MeditationResource\Pages\EditMeditation;
use App\Filament\Resources\MeditationResource\Pages\ListMeditations;
use App\Models\Meditation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_an_admin_can_upload_a_cover_image(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->admin()->create();
        $meditation = Meditation::factory()->create(['audio_path' => 'meditations/cover-test.mp3']);
        Storage::disk('s3')->put($meditation->audio_path, 'fake-audio-content');

        $this->actingAs($admin);

        Livewire::test(EditMeditation::class, ['record' => $meditation->getRouteKey()])
            ->fillForm(['cover_image_path' => UploadedFile::fake()->image('cover.jpg')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $meditation->refresh()->cover_image_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('meditations/covers/', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_an_admin_can_replace_the_cover_image(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->admin()->create();
        $meditation = Meditation::factory()->create([
            'audio_path' => 'meditations/cover-test.mp3',
            'cover_image_path' => 'meditations/covers/old-cover.jpg',
        ]);
        Storage::disk('s3')->put($meditation->audio_path, 'fake-audio-content');
        Storage::disk('s3')->put('meditations/covers/old-cover.jpg', 'old-cover-content');

        $this->actingAs($admin);

        Livewire::test(EditMeditation::class, ['record' => $meditation->getRouteKey()])
            // As in the UI: remove the current cover first, then upload a new one.
            ->fillForm(['cover_image_path' => []])
            ->fillForm(['cover_image_path' => UploadedFile::fake()->image('new-cover.jpg')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $meditation->refresh()->cover_image_path;

        $this->assertNotSame('meditations/covers/old-cover.jpg', $path);
        $this->assertStringStartsWith('meditations/covers/', $path);
        Storage::disk('s3')->assertExists($path);
    }
}
