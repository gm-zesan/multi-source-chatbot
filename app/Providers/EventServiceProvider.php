<?php

namespace App\Providers;

use App\Events\ConversationCreated;
use App\Events\IncomingMessageReceived;
use App\Listeners\ExtractCRMEntitiesListener;
use App\Listeners\RunFAQEngineListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener mappings.
     *
     * Add new listeners here when extending the architecture.
     * Each listener runs independently on its own queue.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        IncomingMessageReceived::class => [
            ExtractCRMEntitiesListener::class,  // crm queue
            RunFAQEngineListener::class,         // faq queue
        ],

        ConversationCreated::class => [
            // Future: LeadScoringListener, WelcomeMessageListener, etc.
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
