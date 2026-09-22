<?php

namespace App\Console\Commands;

use App\Services\AI\CustomerSupportService;
use Illuminate\Console\Command;

class SimulateChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:simulate-chat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a multi-turn Banglish chat with the CustomerSupportService';

    /**
     * Execute the console command.
     */
    public function handle(CustomerSupportService $supportService)
    {
        $this->info("Starting End-to-End Chat Simulation...");
        
        $workspaceId = 1; // Assuming default workspace
        
        // Mock a channel account and conversation
        $channelAccount = \App\Models\ChannelAccount::first();
        if (!$channelAccount) {
            $this->error("No ChannelAccount found. Please seed the database first.");
            return;
        }

        $conversation = \App\Models\Conversation::firstOrCreate(
            ['channel_account_id' => $channelAccount->id, 'external_user_id' => 'sim_user_' . time()],
            ['status' => 'open', 'last_direction' => 'inbound']
        );

        $turns = [
            "Bhai, kemon achen? Apnader return policy ta ki?", // Turn 1: Greeting + Policy query (Banglish)
            "Ami to ekta order korechilam, order id ORD-1234. Status ta ektu bolben?", // Turn 2: Order status (Live Data)
            "Acha, tahole return korle koto din e taka back pabo?", // Turn 3: Follow-up relying on memory/context of return
        ];

        foreach ($turns as $index => $query) {
            $this->warn("\n--- Turn " . ($index + 1) . " ---");
            $this->line("User: " . $query);
            
            try {
                // generateReply signature: (Conversation $conversation, string $query, ?int $workspaceId)
                $response = $supportService->generateReply($conversation, $query, $workspaceId);
                $this->info("Bot: " . $response);
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            }
            
            sleep(1);
        }

        $this->info("\nSimulation Complete.");
    }
}
