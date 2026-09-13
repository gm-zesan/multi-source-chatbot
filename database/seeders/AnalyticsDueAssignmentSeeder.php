<?php

namespace Database\Seeders;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsDueAssignment;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AnalyticsDueAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Salespersons Workspace 1
        $tarek = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-103')->first();
        $mehedi = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-104')->first();

        // Customers Workspace 1
        $rahim = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000001')->first();
        $jamila = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000003')->first();
        $rafiq = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000004')->first();

        // Orders Workspace 1
        $ord1 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-001')->first();
        $ord3 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-003')->first();
        $ord6 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-006')->first();

        // Workspace 2 entities
        $salma = AnalyticsSalesperson::where('workspace_id', $w2->id)->where('employee_code', 'SP-201')->first();
        $nasir = AnalyticsCustomer::where('workspace_id', $w2->id)->where('phone', '01911000099')->first();
        $ordW2 = AnalyticsOrder::where('workspace_id', $w2->id)->where('order_number', 'ORD-W2-001')->first();

        $today = Carbon::now('Asia/Dhaka')->startOfDay();

        // Workspace 1 Due Assignments
        $w1Assignments = [
            // Assignment 1: Rahim's heavy due on ORD-1 assigned to Mehedi
            [
                'order_id' => $ord1->id,
                'customer_id' => $rahim->id,
                'assigned_salesperson_id' => $mehedi->id, // Due Assignee: Mehedi
                'status' => 'in_progress',
                'due_date' => $today->copy()->addDays(7)->toDateString(),
                'notes' => 'Customer promised to clear remaining due by next week via bank transfer.',
            ],
            // Assignment 2: Rafiq's remaining due on ORD-6 assigned to Mehedi
            [
                'order_id' => $ord6->id,
                'customer_id' => $rafiq->id,
                'assigned_salesperson_id' => $mehedi->id, // Due Assignee: Mehedi
                'status' => 'assigned',
                'due_date' => $today->copy()->addDays(5)->toDateString(),
                'notes' => 'First follow-up call scheduled.',
            ],
            // Assignment 3: Jamila's due on ORD-3 assigned to Tarek
            [
                'order_id' => $ord3->id,
                'customer_id' => $jamila->id,
                'assigned_salesperson_id' => $tarek->id, // Due Assignee: Tarek (1 active assignment for Tarek)
                'status' => 'in_progress',
                'due_date' => $today->copy()->addDays(10)->toDateString(),
                'notes' => 'Corporate account with 30-day net terms.',
            ],
        ];

        foreach ($w1Assignments as $data) {
            AnalyticsDueAssignment::updateOrCreate(
                ['workspace_id' => $w1->id, 'order_id' => $data['order_id']],
                $data
            );
        }

        // Workspace 2 Due Assignments
        $w2Assignments = [
            [
                'order_id' => $ordW2->id,
                'customer_id' => $nasir->id,
                'assigned_salesperson_id' => $salma->id,
                'status' => 'assigned',
                'due_date' => $today->copy()->addDays(14)->toDateString(),
                'notes' => 'Workspace B isolated order due.',
            ],
        ];

        foreach ($w2Assignments as $data) {
            AnalyticsDueAssignment::updateOrCreate(
                ['workspace_id' => $w2->id, 'order_id' => $data['order_id']],
                $data
            );
        }
    }
}
