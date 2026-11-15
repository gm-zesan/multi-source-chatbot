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
    ];

    protected $casts = [
        'conversation_count' => 'integer',
        'ai_messages_count' => 'integer',
        'spreadsheet_queries_count' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get or initialize the usage quota record for the current month.
     */
    public static function currentForWorkspace(int $workspaceId): self
    {
        $period = Carbon::now()->format('Y-m');

        return self::firstOrCreate(
            ['workspace_id' => $workspaceId, 'period_year_month' => $period],
            ['conversation_count' => 0, 'ai_messages_count' => 0, 'spreadsheet_queries_count' => 0]
        );
    }
}
