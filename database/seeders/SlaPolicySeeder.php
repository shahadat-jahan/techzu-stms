<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policies = [
            ['priority' => Priority::Low, 'resolution_minutes' => 2880, 'warning_minutes' => 2304], // warning minutes is 80% of resolution minutes
            ['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152], // warning minutes is 80% of resolution minutes
            ['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384], // warning minutes is 80% of resolution minutes
            ['priority' => Priority::Critical, 'resolution_minutes' => 120, 'warning_minutes' => 96], // warning minutes is 80% of resolution minutes
        ];

        foreach ($policies as $policy) {
            SlaPolicy::create($policy);
        }
    }
}
