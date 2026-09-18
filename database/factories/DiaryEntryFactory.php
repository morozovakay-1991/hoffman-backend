<?php

namespace Database\Factories;

use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryEntry>
 */
class DiaryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'diary_day_id' => DiaryDay::factory(),
            'answer_text' => fake()->paragraph(),
            'completed_date' => now()->toDateString(),
            'completed_at' => now(),
            'timezone' => 'UTC',
        ];
    }
}
