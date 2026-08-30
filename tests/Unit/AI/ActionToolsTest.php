<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Tools\CancelOrderTool;
use App\AI\Tools\CreateTicketTool;
use App\AI\Tools\GetOrderTool;
use App\Models\Conversation;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ActionToolsTest extends TestCase
{
    public function test_cancel_order_tool_execution(): void
    {
        $tool = new CancelOrderTool(workspaceId: 1);

        $this->assertSame('CancelOrderTool', $tool->name());
        $this->assertNotEmpty($tool->description());

        $result = (string) $tool->handle(new Request(['order_id' => 1024, 'reason' => 'Changed mind']));
        $this->assertStringContainsString('Order #1024 has been successfully cancelled', $result);
        $this->assertStringContainsString('workspace #1', $result);

        // Invalid order id
        $invalidResult = (string) $tool->handle(new Request(['order_id' => 0]));
        $this->assertStringContainsString('Error: Invalid order ID', $invalidResult);
    }

    public function test_get_order_tool_execution(): void
    {
        $tool = new GetOrderTool(workspaceId: 1);

        $this->assertSame('GetOrderTool', $tool->name());

        $result = (string) $tool->handle(new Request(['order_id' => 2048]));
        $this->assertStringContainsString('Order #2048 status:', $result);
        $this->assertStringContainsString('EXP-2048-BD', $result);
    }

    public function test_create_ticket_tool_execution(): void
    {
        $tool = new CreateTicketTool(workspaceId: 1);

        $this->assertSame('CreateTicketTool', $tool->name());

        $result = (string) $tool->handle(new Request([
            'subject'     => 'Urgent Payment Bug',
            'description' => 'Double deduction on invoice #99',
            'priority'    => 'urgent',
        ]));

        $this->assertStringContainsString('Support Ticket #', $result);
        $this->assertStringContainsString('urgent priority', $result);
    }
}
