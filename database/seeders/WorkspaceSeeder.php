<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workspace::updateOrCreate(
            [
                'slug' => 'entrepreneurs-automation',
            ],
            [
                'name' => 'Entrepreneurs Automation',
                'is_active' => true,
            ]
        );
    }
}
