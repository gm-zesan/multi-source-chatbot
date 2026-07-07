<?php

use App\Http\Controllers\AssignRoleController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::match(['GET', 'POST'], '/webhook', [WebhookController::class, 'handle']);

Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/cache-clear', [DashboardController::class,'cacheClear'])->name('cache-clear');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/password-change', [DashboardController::class, 'changePassword'])->name('password-change.profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::resource('conversations',ConversationController::class)->only(['index', 'show']);
    Route::post('/conversations/{conversation}/reply', [ConversationController::class,'reply']);




    Route::resource('/users', UserController::class)->except(['show']);
    Route::resource('/roles', RoleController::class)->except(['show']);
    Route::resource('/assign-roles', AssignRoleController::class)->only(['index', 'store']);


    //message Route
    Route::get('/message', [ContactFormController::class,'index'])->name('message');
    Route::get('/message/read/', [ContactFormController::class, 'read'])->name('message.read');
    Route::get('/message/important/', [ContactFormController::class, 'important'])->name('message.important');
    Route::get('/message/delete/{id}', [ContactFormController::class,'delete'])->name('message.delete');
});



require __DIR__.'/auth.php';
