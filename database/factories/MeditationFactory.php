<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Meditation>
 */
class MeditationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ['ru' => fake()->sentence(3)],
            'short_description' => ['ru' => fake()->sentence()],
            'full_description' => ['ru' => fake()->paragraph()],
            'audio_path' => 'meditations/'.fake()->uuid().'.mp3',
            'duration_seconds' => fake()->numberBetween(60, 1800),
            'is_free' => false,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the meditation is free to access without a subscription.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }

    /**
     * Indicate that the meditation is not published yet.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
