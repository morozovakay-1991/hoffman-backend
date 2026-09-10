<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
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
            'cover_image_path' => 'articles/'.fake()->uuid().'.jpg',
            'published_at' => now(),
            'is_published' => true,
            'is_new' => false,
        ];
    }

    /**
     * Indicate that the article is not published yet.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Indicate that the article is marked as new.
     */
    public function isNew(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_new' => true,
        ]);
    }
}
