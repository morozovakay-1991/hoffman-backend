<?php

namespace Database\Seeders;

use App\Models\DiaryDay;
use Illuminate\Database\Seeder;

class DiaryDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($dayNumber = 1; $dayNumber <= 100; $dayNumber++) {
            DiaryDay::query()->updateOrCreate(
                ['day_number' => $dayNumber],
                [
                    'title' => ['ru' => "День {$dayNumber}: заголовок-заглушка"],
                    'task_text' => ['ru' => "День {$dayNumber}: заголовок-заглушка"],
                ],
            );
        }
    }
}
