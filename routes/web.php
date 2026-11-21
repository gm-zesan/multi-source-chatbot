<?php

declare(strict_types=1);

use App\Enums\Permissions\ConversationPermission;
use App\Enums\Permissions\FAQPermission;
use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\Permissions\MessagePermission;
use App\Enums\Permissions\UserPermission;
use App\Enums\RoleEnum;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChannelIntegrationController;
use App\Http\Controllers\ChatSimulatorController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FAQController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\ObservabilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SpreadsheetImportController;
use App\Http\Controllers\SuperAdminCommercialController;
use App\Http\Controllers\SuperAdminWorkspaceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WidgetChatController;
use App\Http\Controllers\WorkspaceCourierController;
use App\Http\Controllers\WorkspaceOrderController;
use App\Http\Controllers\WorkspaceSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 1. PUBLIC MARKETING & SITEMAP ROUTES
|--------------------------------------------------------------------------
*/
Route::controller(PublicPageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/platform', 'platform')->name('platform');
    Route::get('/knowledge-engine', 'knowledgeEngine')->name('knowledge-engine');
    Route::get('/unified-inbox', 'unifiedInbox')->name('unified-inbox');
    Route::get('/business-intelligence', 'businessIntelligence')->name('business-intelligence');
    Route::get('/automation', 'automation')->name('automation');
    Route::get('/integrations', 'integrations')->name('integrations');
    Route::get('/solutions', 'solutions')->name('solutions');
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/about', 'about')->name('about');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/resources', 'resources')->name('resources');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/privacy', 'privacy')->name('privacy');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/cookies', 'cookies')->name('cookies');
});

// Public Contact Form Submission
Route::post('/contact', [ContactFormController::class, 'store'])->name('contact.store');

// Public XML Sitemap
Route::get('/sitemap.xml', function () {
    return response(file_get_contents(public_path('sitemap.xml')), 200, [
        'Content-Type' => 'application/xml',
    ]);
})->name('sitemap');

// Uptime & Infrastructure Health Check
Route::get('/health', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| 2. PUBLIC INBOUND WEBHOOKS & STOREFRONT WIDGET APIS
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/webhook/{channel?}', [WebhookController::class, 'handle'])->name('webhook.handle');
Route::post('/webhooks/billing/{gateway}', [SuperAdminCommercialController::class, 'handleWebhook'])->name('webhooks.billing');

// Storefront Web Chat Widget
Route::post('/widget/chat', [WidgetChatController::class, 'chat'])->name('widget.chat');
Route::get('/widget/chat', fn () => redirect()->route('widget.demo'));
Route::post('/api/v1/widget/chat', [WidgetChatController::class, 'chat'])->name('api.widget.chat');
Route::get('/widget/demo', fn () => response()->file(public_path('widget/demo.html')))->name('widget.demo');

/*
|--------------------------------------------------------------------------
| 3. CANONICAL REDIRECTS
|--------------------------------------------------------------------------
*/
Route::get('/simulator', fn () => redirect()->route('simulator.index'))->middleware('auth');
Route::get('/knowledge-base', fn () => redirect()->route('knowledge-base.index'))->middleware('auth');
Route::get('/commercial', fn () => redirect()->route('admin.commercial.index'))->middleware('auth');
Route::get('/commercial-dashboard', fn () => redirect()->route('admin.commercial.index'))->middleware('auth');
Route::get('/admin/commercial', fn () => redirect()->route('admin.commercial.index'))->middleware('auth');
Route::get('/admin/workspaces', fn () => redirect()->route('workspaces.index'))->middleware('auth');

/*
|--------------------------------------------------------------------------
| 4. AUTHENTICATED DASHBOARD WORKSPACE ROUTES (/dashboard)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('dashboard')->group(function () {

    // ── 4.1 Executive Dashboard & System Cache ─────────────────────────
    Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/cache-clear', [DashboardController::class, 'cacheClear'])->name('cache-clear');

    // ── 4.2 Customer Conversations & Live Chat (External Customer Portal) 
    // ONLY for customer & business chatting (Strict zero internal leak)
    Route::resource('conversations', ConversationController::class)->only(['index', 'show'])
        ->middleware('permission:' . ConversationPermission::VIEW->value);
    Route::post('/conversations/{conversation}/reply', [ConversationController::class, 'reply'])
        ->name('conversations.reply')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);
    Route::post('/conversations/{conversation}/ai-reply', [ConversationController::class, 'aiReply'])
        ->name('conversations.ai-reply')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);
    Route::post('/conversations/{conversation}/assign', [ConversationController::class, 'assign'])
        ->name('conversations.assign')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);
    Route::post('/conversations/{conversation}/status', [ConversationController::class, 'updateStatus'])
        ->name('conversations.update-status')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);
    Route::post('/conversations/{conversation}/toggle-ai', [ConversationController::class, 'toggleAi'])
        ->name('conversations.toggle-ai')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);
    Route::post('/conversations/{conversation}/regenerate-intelligence', [ConversationController::class, 'regenerateIntelligence'])
        ->name('conversations.regenerate-intelligence')
        ->middleware('permission:' . ConversationPermission::UPDATE->value);

    // ── 4.3 Orders Hub & Courier Delivery (Fulfillment) ────────────────
    Route::get('/orders', [WorkspaceOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/status', [WorkspaceOrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::get('/orders/export', [WorkspaceOrderController::class, 'exportCsv'])->name('orders.export');

    // Courier Logistics & Dispatch (Steadfast, Pathao, RedX)
    Route::get('/workspace/courier', [WorkspaceCourierController::class, 'settings'])->name('workspace.courier.settings');
    Route::post('/workspace/courier', [WorkspaceCourierController::class, 'updateSettings'])->name('workspace.courier.update');
    Route::post('/workspace/courier/ping', [WorkspaceCourierController::class, 'ping'])->name('workspace.courier.ping');
    Route::post('/orders/{order}/send-courier', [WorkspaceCourierController::class, 'sendSingle'])->name('orders.send-courier');
    Route::post('/orders/bulk-send-courier', [WorkspaceCourierController::class, 'bulkSend'])->name('orders.bulk-send-courier');

    // ── 4.4 AI Chat Simulator (Internal Businessman Diagnostics) ──────
    // Internal portal where businessman can inspect internal business intelligence, cost, COGS, margins
    Route::get('/simulator', [ChatSimulatorController::class, 'index'])->name('simulator.index');
    Route::post('/simulator/send', [ChatSimulatorController::class, 'send'])->middleware('throttle:60,1')->name('simulator.send');
    Route::post('/simulator/clear', [ChatSimulatorController::class, 'clear'])->name('simulator.clear');
    Route::post('/simulator/clear-excel', [ChatSimulatorController::class, 'clearExcel'])->name('simulator.clear_excel');
    Route::post('/simulator/select-source', [ChatSimulatorController::class, 'selectSource'])->name('simulator.select_source');
    Route::post('/simulator/switch-workspace', [ChatSimulatorController::class, 'switchWorkspace'])->name('simulator.switch_workspace');

    // ── 4.5 Business Intelligence & Analytics ──────────────────────────
    Route::get('/analytics', [AnalyticsDashboardController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/data', [AnalyticsDashboardController::class, 'data'])->name('analytics.data');

    // ── 4.6 Unified Knowledge Base Engine ──────────────────────────────
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index'])
        ->name('knowledge-base.index')
        ->middleware('permission:' . implode('|', [
            FAQPermission::VIEW->value,
            KnowledgeDocumentPermission::VIEW->value,
        ]));
    Route::post('/knowledge-base/reindex', [KnowledgeBaseController::class, 'reindex'])
        ->name('knowledge-base.reindex')
        ->middleware('permission:' . implode('|', [
            FAQPermission::UPDATE->value,
            FAQPermission::CREATE->value,
        ]));

    // FAQs
    Route::resource('/faqs', FAQController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            FAQPermission::UPDATE->value,
            FAQPermission::DELETE->value,
        ]));
    Route::post('/faqs/{faq}/toggle-active', [FAQController::class, 'toggleActive'])
        ->name('faqs.toggle-active')
        ->middleware('permission:' . FAQPermission::UPDATE->value);
    Route::post('/faqs/{faq}/resync', [FAQController::class, 'resync'])
        ->name('faqs.resync')
        ->middleware('permission:' . FAQPermission::UPDATE->value);
    Route::post('/faqs/{id}/restore', [FAQController::class, 'restore'])
        ->name('faqs.restore')
        ->withTrashed()
        ->middleware('permission:' . FAQPermission::UPDATE->value);
    Route::post('/faqs/bulk-delete', [FAQController::class, 'bulkDelete'])
        ->name('faqs.bulk-delete')
        ->middleware('permission:' . FAQPermission::DELETE->value);

    // Knowledge Documents
    Route::resource('/documents', KnowledgeDocumentController::class)->only(['index', 'create', 'store', 'destroy'])
        ->middleware('permission:' . implode('|', [
            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::DELETE->value,
        ]));
    Route::post('/documents/{id}/restore', [KnowledgeDocumentController::class, 'restore'])
        ->name('documents.restore')
        ->withTrashed()
        ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
    Route::post('/documents/{id}/retry', [KnowledgeDocumentController::class, 'retry'])
        ->name('documents.retry')
        ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
    Route::post('/documents/{document}/update-purpose', [KnowledgeDocumentController::class, 'updatePurpose'])
        ->name('documents.update-purpose')
        ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
    Route::get('/documents/{document}/preview', [KnowledgeDocumentController::class, 'preview'])
        ->name('documents.preview')
        ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);

    // Dynamic Spreadsheet Ingestion & Mapping
    Route::prefix('/documents/{document}/spreadsheet')->name('documents.spreadsheet.')->group(function () {
        Route::get('/inspect', [SpreadsheetImportController::class, 'inspect'])
            ->name('inspect')
            ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);
        Route::get('/suggestions', [SpreadsheetImportController::class, 'mappingSuggestions'])
            ->name('suggestions')
            ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);
        Route::post('/confirm', [SpreadsheetImportController::class, 'confirmMapping'])
            ->name('confirm')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
        Route::get('/status', [SpreadsheetImportController::class, 'status'])
            ->name('status')
            ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);
        Route::get('/errors', [SpreadsheetImportController::class, 'errors'])
            ->name('errors')
            ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);
        Route::post('/retry', [SpreadsheetImportController::class, 'retry'])
            ->name('retry')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
        Route::post('/cancel', [SpreadsheetImportController::class, 'cancel'])
            ->name('cancel')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
        Route::get('/relationships', [SpreadsheetImportController::class, 'relationships'])
            ->name('relationships')
            ->middleware('permission:' . KnowledgeDocumentPermission::VIEW->value);
        Route::post('/relationships/discover', [SpreadsheetImportController::class, 'discoverRelationships'])
            ->name('relationships.discover')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
        Route::post('/relationships/confirm', [SpreadsheetImportController::class, 'confirmRelationship'])
            ->name('relationships.confirm')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
        Route::post('/relationships/reject', [SpreadsheetImportController::class, 'rejectRelationship'])
            ->name('relationships.reject')
            ->middleware('permission:' . KnowledgeDocumentPermission::UPDATE->value);
    });

    // ── 4.7 Omnichannel Channels & Developer APIs ─────────────────────
    Route::get('/channels', [ChannelIntegrationController::class, 'index'])->name('channels.index');
    Route::post('/channels', [ChannelIntegrationController::class, 'store'])->name('channels.store');
    Route::post('/channels/{channelAccount}/toggle', [ChannelIntegrationController::class, 'toggle'])->name('channels.toggle');
    Route::delete('/channels/{channelAccount}', [ChannelIntegrationController::class, 'destroy'])->name('channels.destroy');
    Route::post('/channels/{channelAccount}/ping', [ChannelIntegrationController::class, 'ping'])->name('channels.ping');

    Route::get('/widget', [WidgetChatController::class, 'manage'])->name('widget.manage');

    Route::get('/developer/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/developer/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/developer/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    // ── 4.8 Team & Employee Access Control (RBAC) ──────────────────────
    Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole'])
        ->name('users.assign-role')
        ->middleware('permission:' . UserPermission::UPDATE->value);

    Route::resource('/users', UserController::class)->except(['show'])
        ->middleware('permission:' . implode('|', [
            UserPermission::VIEW->value,
            UserPermission::CREATE->value,
            UserPermission::UPDATE->value,
            UserPermission::DELETE->value,
        ]));

    Route::resource('/roles', RoleController::class)->except(['show'])
        ->middleware('role:' . RoleEnum::SUPERADMIN->value);

    // Platform Contact Messages (Inquiries)
    Route::get('/message', [ContactFormController::class, 'index'])->name('message')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/read/', [ContactFormController::class, 'read'])->name('message.read')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/important/', [ContactFormController::class, 'important'])->name('message.important')
        ->middleware('permission:' . MessagePermission::VIEW->value);
    Route::get('/message/delete/{id}', [ContactFormController::class, 'delete'])->name('message.delete')
        ->middleware('permission:' . MessagePermission::DELETE->value);

    // ── 4.9 Workspace Profile & Metered Billing ────────────────────────
    Route::get('/workspace/settings', [WorkspaceSettingController::class, 'edit'])->name('workspace.settings.edit');
    Route::post('/workspace/settings', [WorkspaceSettingController::class, 'update'])->name('workspace.settings.update');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/recharge', [BillingController::class, 'recharge'])->name('billing.recharge');

    // ── 4.10 Observability, Exports & User Profile ─────────────────────
    Route::get('/observability', [ObservabilityController::class, 'index'])->name('observability.index');

    Route::get('/export/conversation/{conversation}/{format}', [ExportController::class, 'exportConversation'])->name('export.conversation');
    Route::post('/export/analytics', [ExportController::class, 'exportAnalytics'])->name('export.analytics');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/password-change', [DashboardController::class, 'changePassword'])->name('password-change.profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── 4.11 Multi-Tenant Governance (SuperAdmin & Platform Support) ───
    Route::get('/workspaces', [SuperAdminWorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [SuperAdminWorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [SuperAdminWorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('/workspaces/{workspace}/toggle-active', [SuperAdminWorkspaceController::class, 'toggleActive'])->name('workspaces.toggle_active');

    Route::get('/commercial', [SuperAdminCommercialController::class, 'index'])->name('admin.commercial.index');
    Route::post('/admin/commercial/plans', [SuperAdminCommercialController::class, 'storePlan'])->name('admin.commercial.plans.store');
    Route::put('/admin/commercial/plans/{plan}', [SuperAdminCommercialController::class, 'updatePlan'])->name('admin.commercial.plans.update');
    Route::post('/admin/commercial/plans/{plan}/toggle', [SuperAdminCommercialController::class, 'togglePlan'])->name('admin.commercial.plans.toggle');
    Route::delete('/admin/commercial/plans/{plan}', [SuperAdminCommercialController::class, 'destroyPlan'])->name('admin.commercial.plans.destroy');
    Route::post('/admin/commercial/wallets/{workspace}/adjust', [SuperAdminCommercialController::class, 'adjustWallet'])->name('admin.commercial.wallets.adjust');
});

require __DIR__.'/auth.php';
