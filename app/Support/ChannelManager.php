<?php

namespace App\Support;

use App\Channels\Contracts\ChannelDriver;
use App\Channels\Facebook\FacebookDriver;
use App\Channels\Instagram\InstagramDriver;
use App\Channels\Telegram\TelegramDriver;
use App\Channels\Website\WebsiteDriver;
use App\Channels\WhatsApp\WhatsAppDriver;
use InvalidArgumentException;

class ChannelManager
{
    public static function driver(string $slug): ChannelDriver
    {
        return match ($slug) {
            'facebook' => new FacebookDriver(),
            'whatsapp' => new WhatsAppDriver(),
            'instagram' => new InstagramDriver(),
            'telegram' => new TelegramDriver(),
            'website' => new WebsiteDriver(),
            
            default => throw new InvalidArgumentException(
                "Unsupported channel [$slug]"
            ),
        };
    }
}