<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DiaryDayResource\Pages\ListDiaryDays;
use App\Models\DiaryDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiaryDayResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/diary-days')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/diary-days')->assertForbidden();
    }

    public function test_an_admin_can_access_and_search_diary_days_sorted_by_day_number(): void
    {
        $admin = User::factory()->admin()->create();
        $day1 = DiaryDay::factory()->create(['day_number' => 1, 'title' => ['ru' => 'Знакомство с собой']]);
        $day2 = DiaryDay::factory()->create(['day_number' => 2, 'title' => ['ru' => 'Работа с эмоциями']]);

        $this->actingAs($admin)->get('/backend/diary-days')->assertSuccessful();

        Livewire::test(ListDiaryDays::class)
            ->assertCanSeeTableRecords([$day1, $day2], inOrder: true)
            ->searchTable('Знакомство')
            ->assertCanSeeTableRecords([$day1])
            ->assertCanNotSeeTableRecords([$day2]);
    }

    public function test_a_super_admin_can_access_diary_days(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/backend/diary-days')->assertSuccessful();
    }
}
