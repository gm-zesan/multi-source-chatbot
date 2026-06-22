<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/chat',[ChatController::class,'index']);

Route::post('/chat/send',[ChatController::class,'send']);

