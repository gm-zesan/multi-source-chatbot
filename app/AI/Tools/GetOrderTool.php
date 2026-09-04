<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetOrderTool implements Tool
{
    public function __construct(
        private readonly ?int $workspaceId = null,
        private readonly ?Conversation $conversation = null,
    ) {}

    public function name(): string
    {
        return 'GetOrderTool';
    }

    public function description(): Stringable|string
    {
        return 'Retrieve order details, delivery status, and tracking information for an order.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->integer()->description('The numerical ID of the order to look up (e.g. 1024).')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $orderId = (int) ($request['order_id'] ?? 0);

        if ($orderId <= 0) {
            return 'Error: Invalid order ID provided.';
        }

        $effectiveWorkspaceId = $this->workspaceId
            ?? $this->conversation?->channelAccount?->workspace_id
            ?? 1;

        Log::info('[GetOrderTool] Order status requested', [
            'order_id'     => $orderId,
            'workspace_id' => $effectiveWorkspaceId,
        ]);

        return "Order #{$orderId} status: Shipped. Estimated delivery: Tomorrow by 5:00 PM via Express Courier. Tracking ID: EXP-{$orderId}-BD.";
    }
}
