<?php

namespace Database\Seeders;

use App\Models\ChannelAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChannelAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ChannelAccount::updateOrCreate(
            [
                'external_id' => '1229035226952947',
            ],
            [
                'workspace_id'  => 1,
                'channel_id'    => 1,
                'name'          => 'facebook_page',
                'display_name'  => 'Entrepreneurs Automation',
                'access_token'  => env('FB_PAGE_ACCESS_TOKEN'),
                'refresh_token' => null,
                'settings'      => [
                    'page_id' => '1229035226952947',
                ],
                'is_active'     => true,
            ]
        );
    }
}
