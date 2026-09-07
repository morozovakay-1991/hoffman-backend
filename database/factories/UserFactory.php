<?php

namespace Database\Factories;

use App\Enums\GraduateStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'apple_id' => null,
            'google_id' => null,
            'graduate_status' => GraduateStatus::Unverified,
            'timezone' => fake()->timezone(),
            'is_admin' => false,
        ];
    }

    /**
     * Indicate that the user can access the Filament admin panel.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user authenticates via Apple.
     */
    public function withApple(): static
    {
        return $this->state(fn (array $attributes) => [
            'apple_id' => (string) fake()->unique()->numerify('######.##########.####'),
        ]);
    }

    /**
     * Indicate that the user authenticates via Google.
     */
    public function withGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => (string) fake()->unique()->numerify('##################'),
        ]);
    }

    /**
     * Indicate that the user's graduate status has a given value.
     */
    public function graduateStatus(GraduateStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'graduate_status' => $status,
        ]);
    }
}
