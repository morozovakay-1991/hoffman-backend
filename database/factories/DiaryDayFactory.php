<?php

namespace Database\Factories;

use App\Models\DiaryDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryDay>
 */
class DiaryDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_number' => fake()->numberBetween(1, 100),
            'title' => ['ru' => fake()->sentence(3)],
            'task_text' => ['ru' => fake()->paragraph()],
        ];
    }
}
