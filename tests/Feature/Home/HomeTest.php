<?php

namespace Tests\Feature\Home;

use App\Enums\GraduateStatus;
use App\Models\Article;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_has_the_expected_structure(): void
    {
        Meditation::factory()->free()->create();
        Tool::factory()->create();
        Topic::factory()->create();
        Article::factory()->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'meditations' => [
                    '*' => ['id', 'title', 'short_description', 'is_locked'],
                ],
                'tools' => [
                    '*' => ['id', 'title', 'short_description', 'is_locked'],
                ],
                'topics' => [
                    '*' => ['id', 'title', 'subtitle', 'is_locked'],
                ],
                'articles' => [
                    '*' => ['id', 'title', 'short_description', 'is_locked'],
                ],
                'diary_progress' => ['current_day', 'total_days', 'is_available'],
            ],
        ]);
    }

    public function test_guest_only_sees_accessible_content_and_no_diary_progress(): void
    {
        $freeMeditation = Meditation::factory()->free()->create();
        Meditation::factory()->create(); // paid, inaccessible to a guest
        Article::factory()->create();

        $response = $this->getJson('/api/v1/home');
        $response->assertOk();

        $meditationIds = collect($response->json('data.meditations'))->pluck('id');

        $this->assertTrue($meditationIds->contains($freeMeditation->id));
        $this->assertCount(1, $meditationIds);
        $this->assertCount(1, $response->json('data.articles'));

        $response->assertJsonPath('data.diary_progress.is_available', false)
            ->assertJsonPath('data.diary_progress.current_day', null)
            ->assertJsonPath('data.diary_progress.total_days', 100);
    }

    public function test_subscribed_non_confirmed_graduate_sees_paid_content_but_no_diary_progress(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $paidMeditation = Meditation::factory()->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');
        $response->assertOk();

        $meditationIds = collect($response->json('data.meditations'))->pluck('id');
        $this->assertTrue($meditationIds->contains($paidMeditation->id));

        $response->assertJsonPath('data.diary_progress.is_available', false);
    }

    public function test_confirmed_graduate_with_subscription_sees_diary_progress(): void
    {
        for ($dayNumber = 1; $dayNumber <= 100; $dayNumber++) {
            DiaryDay::factory()->create(['day_number' => $dayNumber]);
        }

        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);

        $firstDay = DiaryDay::query()->where('day_number', 1)->firstOrFail();
        DiaryEntry::create([
            'user_id' => $user->id,
            'diary_day_id' => $firstDay->id,
            'answer_text' => 'Done',
            'completed_date' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.diary_progress.is_available', true)
            ->assertJsonPath('data.diary_progress.current_day', 2)
            ->assertJsonPath('data.diary_progress.total_days', 100);
    }

    public function test_each_section_is_limited_to_five_items(): void
    {
        Meditation::factory()->free()->count(7)->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk();
        $this->assertCount(5, $response->json('data.meditations'));
    }
}
