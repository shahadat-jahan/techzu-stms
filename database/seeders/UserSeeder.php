<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->availableAgent()->create([
            'name' => 'Agent 1',
            'email' => 'agent1@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->availableAgent()->create([
            'name' => 'Agent 2',
            'email' => 'agent2@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->availableAgent()->create([
            'name' => 'Agent 3',
            'email' => 'agent3@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Customer 1',
            'email' => 'customer1@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Customer 2',
            'email' => 'customer2@techzu.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Customer 3',
            'email' => 'customer3@techzu.com',
            'password' => bcrypt('password'),
        ]);
    }
}
