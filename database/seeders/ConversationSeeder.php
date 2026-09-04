<?php

namespace Database\Seeders;

use App\Models\Conversation;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conversations = array(
            array('id' => '1','channel_account_id' => '1','external_user_id' => '26713263338348454','customer_name' => 'GM Zesan','customer_avatar' => 'https://platform-lookaside.fbsbx.com/platform/profilepic/?eai=Aa3a9Jby5tKBpTEFv9XyanaI-1BcJhsHWzCtFNgtqIWBO-zKgE0w4UsfkkkncICpl34pRkWLuJCQnA&psid=26713263338348454&width=1024&ext=1785525345&hash=Afs2OOk00fw9qUrAn12bqYpu','last_message' => 'sadfca','last_message_at' => '2026-07-02 07:09:06','last_direction' => 'outbound','unread_count' => '1','status' => 'open','metadata' => NULL,'created_at' => '2026-07-01 19:15:45','updated_at' => '2026-07-02 07:09:06')
        );
        foreach ($conversations as $conversation) {
            Conversation::create($conversation);
        }
    }
}
