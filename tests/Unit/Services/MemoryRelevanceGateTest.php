<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Services\Memory\MemoryRelevanceGate;
use Tests\TestCase;

class MemoryRelevanceGateTest extends TestCase
{
    private MemoryRelevanceGate $gate;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new MemoryRelevanceGate();
        $this->conversation = new Conversation([
            'external_user_id' => 'test_user_gate_101',
        ]);
    }

    public function test_null_conversation_is_gated_out(): void
    {
        $this->assertFalse($this->gate->shouldRetrieve('I want size XL', null));
    }

    public function test_short_queries_are_gated_out(): void
    {
        $this->assertFalse($this->gate->shouldRetrieve('hi', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('ok', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('  ', $this->conversation));
    }

    public function test_pure_chit_chat_is_gated_out(): void
    {
        $this->assertFalse($this->gate->shouldRetrieve('hello', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('good morning', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('thank you', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('dhonnobad', $this->conversation));
    }

    public function test_commercial_and_personal_intents_pass_through(): void
    {
        $this->assertTrue($this->gate->shouldRetrieve('What is my payment method?', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('Do you have size XL in black?', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('Where is my order #1042?', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('I received a damaged item yesterday', $this->conversation));
    }

    public function test_bengali_intents_pass_through(): void
    {
        $this->assertTrue($this->gate->shouldRetrieve('আমার পেমেন্ট মেথড পরিবর্তন করতে চাই', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('আমার অর্ডার স্ট্যাটাস কী?', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('সাইজ কত পাওয়া যাবে?', $this->conversation));
    }
}
