<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
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
            'subtitle' => ['ru' => fake()->sentence()],
            'full_description' => ['ru' => fake()->paragraph()],
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the topic is not published yet.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
