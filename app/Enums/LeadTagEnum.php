<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadTagEnum: string
{
    case HOT_LEAD = 'hot_lead';
    case ORDER_PLACED = 'order_placed';
    case PRICE_INQUIRY = 'price_inquiry';
    case COMPLAINT = 'complaint';
    case HUMAN_REQUESTED = 'human_requested';
    case GENERAL_FAQ = 'general_faq';

    public function label(): string
    {
        return match ($this) {
            self::HOT_LEAD        => 'Hot Lead',
            self::ORDER_PLACED    => 'Order Placed',
            self::PRICE_INQUIRY   => 'Price Inquiry',
            self::COMPLAINT       => 'Complaint / Return',
            self::HUMAN_REQUESTED => 'Human Support',
            self::GENERAL_FAQ     => 'General FAQ',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::HOT_LEAD        => '🔥',
            self::ORDER_PLACED    => '📦',
            self::PRICE_INQUIRY   => '💰',
            self::COMPLAINT       => '⚠️',
            self::HUMAN_REQUESTED => '👤',
            self::GENERAL_FAQ     => '❓',
        };
    }

    public function fullLabel(): string
    {
        return "{$this->emoji()} {$this->label()}";
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::HOT_LEAD        => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20',
            self::ORDER_PLACED    => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
            self::PRICE_INQUIRY   => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20',
            self::COMPLAINT       => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
            self::HUMAN_REQUESTED => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20',
            self::GENERAL_FAQ     => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20',
        };
    }

    /**
     * @return array<string, array{slug: string, label: string, emoji: string, full_label: string, badge_classes: string}>
     */
    public static function allDetails(): array
    {
        $details = [];
        foreach (self::cases() as $case) {
            $details[$case->value] = [
                'slug'          => $case->value,
                'label'         => $case->label(),
                'emoji'         => $case->emoji(),
                'full_label'    => $case->fullLabel(),
                'badge_classes' => $case->badgeClasses(),
            ];
        }

        return $details;
    }
}
