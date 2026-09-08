<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['app_store', 'google_play', 'stripe']),
            'product_id' => fake()->randomElement(['monthly_plan', 'yearly_plan', 'lifetime_plan']),
            'transaction_id' => fake()->unique()->uuid(),
            'original_transaction_id' => fake()->uuid(),
            'status' => 'active',
            'auto_renew' => true,
            'starts_at' => $startsAt,
            'trial_ends_at' => null,
            'expires_at' => fake()->dateTimeBetween('now', '+1 year'),
            'cancelled_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the subscription has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'auto_renew' => false,
            'expires_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the subscription was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);
    }
}
