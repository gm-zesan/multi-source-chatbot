<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $channels = [
            [
                'slug' => 'facebook',
                'name' => 'Facebook',
                'icon' => 'fab fa-facebook'
            ],
            [
                'slug' => 'whatsapp',
                'name' => 'WhatsApp',
                'icon' => 'fab fa-whatsapp'
            ],
            [
                'slug' => 'instagram',
                'name' => 'Instagram',
                'icon' => 'fab fa-instagram'
            ],
            [
                'slug' => 'telegram',
                'name' => 'Telegram',
                'icon' => 'fab fa-telegram'
            ],
            [
                'slug' => 'website',
                'name' => 'Website Chat',
                'icon' => 'fas fa-globe'
            ],
        ];

        foreach ($channels as $channel) {
            Channel::updateOrCreate(
                ['slug' => $channel['slug']],
                $channel
            );
        }
    }
}
