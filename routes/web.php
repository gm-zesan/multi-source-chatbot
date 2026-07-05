<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::match(['GET', 'POST'], '/webhook', [WebhookController::class, 'handle']);
    Route::resource('conversations',ConversationController::class)->only(['index', 'show']);
    Route::post('/conversations/{conversation}/reply', [ConversationController::class,'reply']);
});

require __DIR__.'/auth.php';
