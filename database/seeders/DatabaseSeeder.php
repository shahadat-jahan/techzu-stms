<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            SlaPolicySeeder::class,
            BusinessHourSeeder::class,
            // DemoDataSeeder::class, // opt-in: php artisan db:seed --class=DemoDataSeeder
        ]);
    }
}
