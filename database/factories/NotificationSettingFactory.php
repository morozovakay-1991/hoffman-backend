<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationSetting>
 */
class NotificationSettingFactory extends Factory
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
            'push_enabled' => true,
            'email_enabled' => true,
            'marketing_enabled' => false,
            'daily_practices_enabled' => true,
            'new_articles_enabled' => true,
            'system_enabled' => true,
        ];
    }
}
