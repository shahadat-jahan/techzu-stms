<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Technical Support' => 'technical-support',
            'Billing' => 'billing',
            'General Inquiry' => 'general-inquiry',
            'Feature Request' => 'feature-request',
            'Bug Report' => 'bug-report',
        ];

        foreach ($categories as $name => $slug) {
            Category::create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
            ]);
        }

        $this->mapAgentsToCategories();
    }

    private function mapAgentsToCategories(): void
    {
        $agent1 = User::where('email', 'agent1@techzu.com')->first();
        $agent2 = User::where('email', 'agent2@techzu.com')->first();
        $agent3 = User::where('email', 'agent3@techzu.com')->first();

        $mappings = [
            'Technical Support' => [$agent1->id],
            'Bug Report' => [$agent1->id],
            'Billing' => [$agent2->id],
            'General Inquiry' => [$agent2->id],
            'Feature Request' => [$agent3->id],
        ];

        foreach ($mappings as $categoryName => $agentIds) {
            $category = Category::where('name', $categoryName)->first();
            $category->agents()->attach($agentIds);
        }
    }
}
