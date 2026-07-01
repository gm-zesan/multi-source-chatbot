<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Root redirects to the chat interface
Route::redirect('/', '/chat');

Route::get('/chat', [ChatController::class, 'index']);
Route::post('/chat/send', [ChatController::class, 'send']);


Route::match(['GET', 'POST'], '/webhook', [WebhookController::class, 'handle']);
Route::resource('conversations',ConversationController::class)->only(['index', 'show']);
Route::post('/conversations/{conversation}/reply', [ConversationController::class,'reply']);
