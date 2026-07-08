<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Run with: php artisan db:seed
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            WorkspaceSeeder::class,
            UserSeeder::class,
            ChannelSeeder::class,
            ChannelAccountSeeder::class,
            ConversationSeeder::class,
            MessageSeeder::class,
            FAQCategorySeeder::class,
            FAQSeeder::class,
            KnowledgeSearchLogSeeder::class,
            UnansweredQuestionSeeder::class,
        ]);
    }
}
