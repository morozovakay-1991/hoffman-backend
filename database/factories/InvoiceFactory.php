<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
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
            'subscription_id' => Subscription::factory(),
            'provider' => fake()->randomElement(['stripe', 'cloudpayments']),
            'external_invoice_id' => fake()->uuid(),
            'amount' => fake()->numberBetween(500, 5000),
            'currency' => fake()->randomElement(['USD', 'RUB']),
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => [],
        ];
    }
}
