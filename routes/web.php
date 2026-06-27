<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Root redirects to the chat interface
Route::redirect('/', '/chat');

Route::get('/chat', [ChatController::class, 'index']);

Route::post('/chat/send', [ChatController::class, 'send']);

