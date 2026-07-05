<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $messages = array(
            array('id' => '1','conversation_id' => '1','external_message_id' => 'm_sYPjV_dmVcDHMdlPReDlDL6L5gXx7iRrXB5tzEOQX88-twWYVJi4xLypMCB4rYfjg4gmB_JW88BPx24CQN_kAg','direction' => 'inbound','type' => 'text','body' => 'hello','status' => 'sent','metadata' => '{"external_user_id":"26713263338348454","external_message_id":"m_sYPjV_dmVcDHMdlPReDlDL6L5gXx7iRrXB5tzEOQX88-twWYVJi4xLypMCB4rYfjg4gmB_JW88BPx24CQN_kAg","text":"hello","attachments":[],"customer_name":"GM Zesan","customer_avatar":"https:\\/\\/platform-lookaside.fbsbx.com\\/platform\\/profilepic\\/?eai=Aa3a9Jby5tKBpTEFv9XyanaI-1BcJhsHWzCtFNgtqIWBO-zKgE0w4UsfkkkncICpl34pRkWLuJCQnA&psid=26713263338348454&width=1024&ext=1785525345&hash=Afs2OOk00fw9qUrAn12bqYpu"}','created_at' => '2026-07-01 19:15:45','updated_at' => '2026-07-01 19:15:45'),
            array('id' => '2','conversation_id' => '1','external_message_id' => 'm_QKSqsjS3ML59ao2oKRo3mb6L5gXx7iRrXB5tzEOQX8-4e13wJhA7y4YLyyIeFw7K50wMjGG6_2mvmXtMOwkRuw','direction' => 'outbound','type' => 'text','body' => 'kmn asen?','status' => 'sent','metadata' => '{"recipient_id":"26713263338348454","message_id":"m_QKSqsjS3ML59ao2oKRo3mb6L5gXx7iRrXB5tzEOQX8-4e13wJhA7y4YLyyIeFw7K50wMjGG6_2mvmXtMOwkRuw"}','created_at' => '2026-07-01 19:16:01','updated_at' => '2026-07-01 19:16:01'),
            array('id' => '3','conversation_id' => '1','external_message_id' => 'm_90JeROv-YwdqYUgWfKIbyr6L5gXx7iRrXB5tzEOQX89jj05UbvBDwMWz9igGs2COeKPGvtzWsfWIT5y9_qRWOA','direction' => 'inbound','type' => 'text','body' => 'ki koro vai?','status' => 'sent','metadata' => '{"external_user_id":"26713263338348454","external_message_id":"m_90JeROv-YwdqYUgWfKIbyr6L5gXx7iRrXB5tzEOQX89jj05UbvBDwMWz9igGs2COeKPGvtzWsfWIT5y9_qRWOA","text":"ki koro vai?","attachments":[],"customer_name":"GM Zesan","customer_avatar":"https:\\/\\/platform-lookaside.fbsbx.com\\/platform\\/profilepic\\/?eai=Aa1iWJSWoUcewZVWHYgBJoYnkSDoWDVe7Cfsi1KHBaZonIIXXOzpZ7TZPUVDYiKe_sdF77JUVIJoIA&psid=26713263338348454&width=1024&ext=1785565142&hash=Afto_pZSEom0bV90wHAiRGpY"}','created_at' => '2026-07-02 06:19:03','updated_at' => '2026-07-02 06:19:03'),
            array('id' => '4','conversation_id' => '1','external_message_id' => 'm_Jz43QqcZG7R1rLb5Jg7kZr6L5gXx7iRrXB5tzEOQX89aS1qXjJW0XeSkFn16te_SnQ33G-Q8MhR_R2le_RGLmw','direction' => 'outbound','type' => 'text','body' => 'ei to nothing..','status' => 'sent','metadata' => '{"recipient_id":"26713263338348454","message_id":"m_Jz43QqcZG7R1rLb5Jg7kZr6L5gXx7iRrXB5tzEOQX89aS1qXjJW0XeSkFn16te_SnQ33G-Q8MhR_R2le_RGLmw"}','created_at' => '2026-07-02 06:19:31','updated_at' => '2026-07-02 06:19:31'),
            array('id' => '5','conversation_id' => '1','external_message_id' => 'm_dh-d1xLKXU4nFyLjCpv3K76L5gXx7iRrXB5tzEOQX88vITZri0u-n1fVPm7S7gm86nAtNSobHK2lE7Fq9P6mcQ','direction' => 'outbound','type' => 'text','body' => 'hello brother!!','status' => 'sent','metadata' => '{"recipient_id":"26713263338348454","message_id":"m_dh-d1xLKXU4nFyLjCpv3K76L5gXx7iRrXB5tzEOQX88vITZri0u-n1fVPm7S7gm86nAtNSobHK2lE7Fq9P6mcQ"}','created_at' => '2026-07-02 06:58:26','updated_at' => '2026-07-02 06:58:26'),
            array('id' => '6','conversation_id' => '1','external_message_id' => 'm_2oMuMlfTxgRjxFgG8Qiy-b6L5gXx7iRrXB5tzEOQX89aVMW-VI86LuOp09ApsdyzQrVa4yxm6SPKR_cltxaioA','direction' => 'outbound','type' => 'text','body' => 'jhghbh','status' => 'sent','metadata' => '{"recipient_id":"26713263338348454","message_id":"m_2oMuMlfTxgRjxFgG8Qiy-b6L5gXx7iRrXB5tzEOQX89aVMW-VI86LuOp09ApsdyzQrVa4yxm6SPKR_cltxaioA"}','created_at' => '2026-07-02 07:00:24','updated_at' => '2026-07-02 07:00:24'),
            array('id' => '7','conversation_id' => '1','external_message_id' => 'm_NYqoNrDLwODhTTNec4qjIr6L5gXx7iRrXB5tzEOQX8913KypbRgLxCkq7Nb-FrVWtyZE5MhSrAKf47HLOPeZwQ','direction' => 'outbound','type' => 'text','body' => 'bvvg','status' => 'sent','metadata' => '{"recipient_id":"26713263338348454","message_id":"m_NYqoNrDLwODhTTNec4qjIr6L5gXx7iRrXB5tzEOQX8913KypbRgLxCkq7Nb-FrVWtyZE5MhSrAKf47HLOPeZwQ"}','created_at' => '2026-07-02 07:02:15','updated_at' => '2026-07-02 07:02:15')
        );

        foreach ($messages as $message) {
            Message::create($message);
        }
    }
}
