<?php

namespace Database\Seeders;

use App\Models\BusinessHour;
use Illuminate\Database\Seeder;

class BusinessHourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hours = [
            ['day_of_week' => 0, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '18:00:00'],
            ['day_of_week' => 1, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '18:00:00'],
            ['day_of_week' => 2, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '18:00:00'],
            ['day_of_week' => 3, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '18:00:00'],
            ['day_of_week' => 4, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '18:00:00'],
            ['day_of_week' => 5, 'is_open' => false, 'open_time' => '00:00:00', 'close_time' => '00:00:00'],
            ['day_of_week' => 6, 'is_open' => false, 'open_time' => '00:00:00', 'close_time' => '00:00:00'],
        ];

        foreach ($hours as $hour) {
            BusinessHour::create($hour);
        }
    }
}
