<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Web Chat Widget Synchronous Interaction Route
Route::post('/v1/widget/chat', [\App\Http\Controllers\WidgetChatController::class, 'chat'])->name('api.v1.widget.chat');
Route::post('/widget/chat', [\App\Http\Controllers\WidgetChatController::class, 'chat'])->name('api.widget.chat.v1');
