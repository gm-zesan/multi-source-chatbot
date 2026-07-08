<?php

use App\Enums\Permissions\ConversationPermission;
use App\Enums\Permissions\MessagePermission;
use App\Enums\Permissions\RolePermission;
use App\Enums\Permissions\UserPermission;
use App\Http\Controllers\AssignRoleController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FAQCategoryController;
use App\Http\Controllers\FAQController;
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


    // Conversations
    Route::resource('conversations',ConversationController::class)->only(['index', 'show'])
        ->middleware('permission:' . ConversationPermission::VIEW->value);
    Route::post('/conversations/{conversation}/reply', [ConversationController::class,'reply'])
        ->middleware('permission:' . ConversationPermission::UPDATE->value);

    // Users
    Route::resource('/users', UserController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            UserPermission::VIEW->value,
            UserPermission::CREATE->value,
            UserPermission::UPDATE->value,
            UserPermission::DELETE->value,
        ]));

    // Roles
    Route::resource('/roles', RoleController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            RolePermission::VIEW->value,
            RolePermission::CREATE->value,
            RolePermission::UPDATE->value,
            RolePermission::DELETE->value,
        ]));

    // Assign Roles
    Route::resource('/assign-roles', AssignRoleController::class)->only(['index', 'store'])
        ->middleware('permission:' . RolePermission::VIEW->value);

    // Messages
    Route::get('/message', [ContactFormController::class,'index'])->name('message')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/read/', [ContactFormController::class, 'read'])->name('message.read')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/important/', [ContactFormController::class, 'important'])->name('message.important')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/delete/{id}', [ContactFormController::class,'delete'])->name('message.delete')
        ->middleware('permission:' . MessagePermission::DELETE->value);

    // ── FAQ Categories ─────────────────────────────────────────────────
    Route::resource('/faq-categories', FAQCategoryController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            \App\Enums\Permissions\FAQPermission::VIEW->value,
            \App\Enums\Permissions\FAQPermission::CREATE->value,
            \App\Enums\Permissions\FAQPermission::UPDATE->value,
            \App\Enums\Permissions\FAQPermission::DELETE->value,
        ]));
    Route::post('/faq-categories/{faqCategory}/toggle-active', [FAQCategoryController::class, 'toggleActive'])
        ->name('faq-categories.toggle-active')
        ->middleware('permission:' . \App\Enums\Permissions\FAQPermission::UPDATE->value);
    Route::post('/faq-categories/{id}/restore', [FAQCategoryController::class, 'restore'])
        ->name('faq-categories.restore')
        ->withTrashed()
        ->middleware('permission:' . \App\Enums\Permissions\FAQPermission::UPDATE->value);

    // ── FAQs ────────────────────────────────────────────────────────────
    Route::resource('/faqs', FAQController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            \App\Enums\Permissions\FAQPermission::VIEW->value,
            \App\Enums\Permissions\FAQPermission::CREATE->value,
            \App\Enums\Permissions\FAQPermission::UPDATE->value,
            \App\Enums\Permissions\FAQPermission::DELETE->value,
        ]));
    Route::post('/faqs/{faq}/toggle-active', [FAQController::class, 'toggleActive'])
        ->name('faqs.toggle-active')
        ->middleware('permission:' . \App\Enums\Permissions\FAQPermission::UPDATE->value);
    Route::post('/faqs/{id}/restore', [FAQController::class, 'restore'])
        ->name('faqs.restore')
        ->withTrashed()
        ->middleware('permission:' . \App\Enums\Permissions\FAQPermission::UPDATE->value);
    Route::post('/faqs/bulk-delete', [FAQController::class, 'bulkDelete'])
        ->name('faqs.bulk-delete')
        ->middleware('permission:' . \App\Enums\Permissions\FAQPermission::DELETE->value);
});

require __DIR__.'/auth.php';
