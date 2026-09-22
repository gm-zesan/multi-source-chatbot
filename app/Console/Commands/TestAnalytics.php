<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsClient;
use Illuminate\Console\Command;

class TestAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-analytics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AnalyticsClient Python integration';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsClient $analyticsClient)
    {
        $this->info("Starting Analytics & CRM Integration Verification...");
        
        $workspaceId = 1;
        $query = "month wise sale data daw?";
        
        $this->line("Sending query: " . $query);
        
        try {
            $result = $analyticsClient->query($query, $workspaceId);
            
            if ($result['success']) {
                $this->info("Success! Analytics service responded correctly.");
                $this->line("Engine: " . $result['engine']);
                $this->line("Intent: " . $result['intent']);
                $this->line("Latency (ms): " . $result['latency_ms']);
                $this->line("Report:\n" . $result['report']);
            } else {
                $this->error("Analytics query failed internally: " . json_encode($result, JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $this->error("Error communicating with Analytics service: " . $e->getMessage());
        }
        
        $this->info("\nVerification Complete.");
    }
}
