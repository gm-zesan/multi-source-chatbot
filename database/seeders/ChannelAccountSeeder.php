<?php

namespace Database\Seeders;

use App\Models\Channel;
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
        $facebook = Channel::where('slug', 'facebook')->firstOrFail();
        ChannelAccount::updateOrCreate(
            [
                'channel_id'    => $facebook->id,
                'external_id'   => '1229035226952947',
            ],
            [
                'workspace_id'  => 1,
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
