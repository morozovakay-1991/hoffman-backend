<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VerificationRequest>
 */
class VerificationRequestFactory extends Factory
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
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'phone' => fake()->numerify('+7##########'),
            'status' => VerificationStatus::Pending,
        ];
    }
}
