<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceUsageQuota extends Model
{
    use HasFactory;

    protected $table = 'workspace_usage_quotas';

    protected $fillable = [
        'workspace_id',
        'period_year_month',
        'conversation_count',
        'ai_messages_count',
        'spreadsheet_queries_count',
        'whatsapp_messages_sent',
        'messenger_messages_sent',
        'whatsapp_free_limit',
        'zero_charge_protection',
        'quota_warning_sent_at',
        'quota_exhausted_sent_at',
    ];

    protected $casts = [
        'conversation_count' => 'integer',
        'ai_messages_count' => 'integer',
        'spreadsheet_queries_count' => 'integer',
        'whatsapp_messages_sent' => 'integer',
        'messenger_messages_sent' => 'integer',
        'whatsapp_free_limit' => 'integer',
        'zero_charge_protection' => 'boolean',
        'quota_warning_sent_at' => 'datetime',
        'quota_exhausted_sent_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if WhatsApp monthly free quota is completely exhausted.
     */
    public function isWhatsAppQuotaExhausted(): bool
    {
        return $this->whatsapp_messages_sent >= max(1, $this->whatsapp_free_limit);
    }

    /**
     * Check if WhatsApp monthly free quota is near exhaustion (>= 80%).
     */
    public function isWhatsAppQuotaNearLimit(): bool
    {
        return $this->whatsappUsagePercentage() >= 80.0;
    }

    /**
     * Number of remaining free WhatsApp messages this month.
     */
    public function whatsAppRemaining(): int
    {
        return max(0, $this->whatsapp_free_limit - $this->whatsapp_messages_sent);
    }

    /**
     * Usage percentage of WhatsApp free tier.
     */
    public function whatsAppUsagePercentage(): float
    {
        $limit = max(1, $this->whatsapp_free_limit);
        return min(100.0, round(($this->whatsapp_messages_sent / $limit) * 100, 1));
    }

    /**
     * Get or initialize the usage quota record for the current month.
     */
    public static function currentForWorkspace(int $workspaceId): self
    {
        $period = Carbon::now()->format('Y-m');

        return self::firstOrCreate(
            ['workspace_id' => $workspaceId, 'period_year_month' => $period],
            [
                'conversation_count'        => 0,
                'ai_messages_count'         => 0,
                'spreadsheet_queries_count' => 0,
                'whatsapp_messages_sent'    => 0,
                'messenger_messages_sent'   => 0,
                'whatsapp_free_limit'       => 1000,
                'zero_charge_protection'    => true,
            ]
        );
    }
}
