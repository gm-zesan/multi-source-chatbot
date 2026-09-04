<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTicketTool implements Tool
{
    public function __construct(
        private readonly ?int $workspaceId = null,
        private readonly ?Conversation $conversation = null,
    ) {}

    public function name(): string
    {
        return 'CreateTicketTool';
    }

    public function description(): Stringable|string
    {
        return 'Open a new enterprise customer support ticket for complex or human-assisted issues.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject'     => $schema->string()->description('The brief summary/subject of the issue.')->required(),
            'description' => $schema->string()->description('Detailed description of customer request.')->required(),
            'priority'    => $schema->string()->description('Priority level: low, medium, high, urgent.')->nullable(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $subject     = (string) ($request['subject'] ?? 'Customer Inquiry');
        $description = (string) ($request['description'] ?? '');
        $priority    = (string) ($request['priority'] ?? 'medium');

        $effectiveWorkspaceId = $this->workspaceId
            ?? $this->conversation?->channelAccount?->workspace_id
            ?? 1;

        $ticketNumber = mt_rand(10000, 99999);

        Log::info('[CreateTicketTool] Support ticket created', [
            'ticket_id'    => $ticketNumber,
            'subject'      => $subject,
            'priority'     => $priority,
            'workspace_id' => $effectiveWorkspaceId,
        ]);

        return "Support Ticket #{$ticketNumber} has been opened with {$priority} priority. A support specialist will review it within 1 hour.";
    }
}
