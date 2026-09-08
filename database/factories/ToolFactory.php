<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tool>
 */
class ToolFactory extends Factory
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
            'is_published' => true,
        ];
    }

    /**
     * Indicate that the tool is not published yet.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
