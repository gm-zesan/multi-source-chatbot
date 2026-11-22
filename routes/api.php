<?php

declare(strict_types=1);

use App\Http\Controllers\Api\v1\ChatApiController;
use App\Http\Controllers\Api\v1\KnowledgeApiController;
use App\Http\Controllers\Api\v1\WorkspaceApiController;
use App\Http\Controllers\WidgetChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Web Chat Widget Synchronous Interaction Route (Public, rate-limited to 60 req/min)
Route::post('/v1/widget/chat', [WidgetChatController::class, 'chat'])->middleware('throttle:60,1')->name('api.v1.widget.chat');
Route::post('/widget/chat', [WidgetChatController::class, 'chat'])->middleware('throttle:60,1')->name('api.widget.chat.v1');

// Inbound Courier Status Webhooks (Steadfast, Pathao)
Route::post('/v1/courier/webhook/steadfast', [\App\Http\Controllers\WorkspaceCourierController::class, 'handleSteadfastWebhook'])->name('api.courier.webhook.steadfast');
Route::post('/v1/courier/webhook/pathao', [\App\Http\Controllers\WorkspaceCourierController::class, 'handlePathaoWebhook'])->name('api.courier.webhook.pathao');

// External Developer REST API v1 (Secured via API Key & Rate Limiting)
Route::prefix('v1')->middleware(['auth.api_key', 'throttle:60,1'])->group(function () {
    // 1. Programmatic AI Chat Completion
    Route::post('/chat', [ChatApiController::class, 'send'])->name('api.v1.chat');

    // 2. Knowledge Retrieval Endpoints
    Route::get('/knowledge/faqs', [KnowledgeApiController::class, 'faqs'])->name('api.v1.knowledge.faqs');
    Route::get('/knowledge/documents', [KnowledgeApiController::class, 'documents'])->name('api.v1.knowledge.documents');

    // 3. Workspace Metadata & Usage Quota
    Route::get('/workspace', [WorkspaceApiController::class, 'info'])->name('api.v1.workspace.info');
});
