<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters:
     *   1. User factory (default Laravel)
     *   2. EmbeddingSeeder (context routing vectors)
     *
     * Run with: php artisan db:seed
     */
    public function run(): void
    {
        $this->call([
            EmbeddingSeeder::class,
            WorkspaceSeeder::class,
            ChannelSeeder::class,
            ChannelAccountSeeder::class,
        ]);
    }
}
