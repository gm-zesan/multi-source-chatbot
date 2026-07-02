<?php

namespace App\Support;

class ChannelIcon
{
    public static function icon(string $slug): string
    {
        return match($slug){

            'facebook'=>'bi bi-messenger',

            'whatsapp'=>'bi bi-whatsapp',

            'instagram'=>'bi bi-instagram',

            'telegram'=>'bi bi-telegram',

            default=>'bi bi-chat-dots',

        };
    }

    public static function color(string $slug): string
    {
        return match($slug){

            'facebook'=>'#1877F2',

            'whatsapp'=>'#25D366',

            'instagram'=>'#E4405F',

            'telegram'=>'#229ED9',

            default=>'#64748B',

        };
    }
}