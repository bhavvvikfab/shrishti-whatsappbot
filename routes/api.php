<?php

use App\Http\Controllers\Api\CustomerController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\Masters;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\DashboardController as WebDashboardController;
use App\Http\Controllers\Api\Masters\StageController as ApiStageController;
use App\Http\Controllers\Api\ProductCategoryController as ApiProductCategoryController;
use App\Http\Controllers\Api\StatusHistoryController as ApiStatusHistoryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\WhatsAppApiController;
use App\Http\Controllers\Api\AutomationApiController;
use App\Http\Controllers\Api\CampaignApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('login', function()
    {
        dd('hh');
    })->middleware('throttle:5,1');

    // Debug endpoint (remove in production)
    Route::get('debug/user', function () {
        $user = auth()->user();
        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'roles' => $user->roles()->pluck('name')->toArray(),
            ] : null,
            'token' => request()->bearerToken() ? substr(request()->bearerToken(), 0, 20) . '...' : null,
        ]);
    })->middleware('auth:sanctum');

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::post('update', [ProfileController::class, 'update']);
            Route::post('password', [ProfileController::class, 'updatePassword']);
        });

        // Settings
        Route::get('settings', [SettingController::class, 'index'])->name('api.settings.index');
        Route::get('settings/{key}', [SettingController::class, 'show'])->name('api.settings.show');
        Route::match(['put', 'post'], 'settings', [SettingController::class, 'update'])->name('api.settings.update');

        // Permissions
        Route::get('permissions', [RoleController::class, 'permissions']);
    });
});

// API routes without v1 prefix (for frontend compatibility)
// These routes use custom 'auth.api' middleware for proper API authentication
Route::middleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    'auth.api',
])->group(function () {
    // Core CRM API endpoints
    Route::apiResource('leads', LeadController::class)->names([
        'index' => 'api.leads.index',
        'store' => 'api.leads.store',
        'show' => 'api.leads.show',
        'update' => 'api.leads.update',
        'destroy' => 'api.leads.destroy',
    ]);
    
    Route::apiResource('deals', DealController::class)->names([
        'index' => 'api.deals.index',
        'store' => 'api.deals.store',
        'show' => 'api.deals.show',
        'update' => 'api.deals.update',
        'destroy' => 'api.deals.destroy',
    ]);

    Route::apiResource('follow-ups', FollowUpController::class)->names([
        'index' => 'api.follow_ups.index',
        'store' => 'api.follow_ups.store',
        'show' => 'api.follow_ups.show',
        'update' => 'api.follow_ups.update',
        'destroy' => 'api.follow_ups.destroy',
    ]);

    Route::apiResource('projects', ProjectController::class)->names([
        'index' => 'api.projects.index',
        'store' => 'api.projects.store',
        'show' => 'api.projects.show',
        'update' => 'api.projects.update',
        'destroy' => 'api.projects.destroy',
    ]);

    Route::apiResource('meetings', MeetingController::class)->names([
        'index' => 'api.meetings.index',
        'store' => 'api.meetings.store',
        'show' => 'api.meetings.show',
        'update' => 'api.meetings.update',
        'destroy' => 'api.meetings.destroy',
    ]);
    Route::post('meetings/{meeting}/status', [MeetingController::class, 'updateStatus'])->name('api.meetings.status');
    
    // Meeting helper routes
    Route::get('meetings/customers', [MeetingController::class, 'getCustomers'])->name('api.meetings.customers');
    Route::get('meetings/users', [MeetingController::class, 'getUsers'])->name('api.meetings.users');
    
    // Google Calendar routes
    Route::get('meetings/google/auth-status', [MeetingController::class, 'googleAuthStatus'])->name('api.meetings.google.status');
    Route::get('meetings/google/auth-url', [MeetingController::class, 'googleAuthUrl'])->name('api.meetings.google.auth_url');
    Route::post('meetings/google/callback', [MeetingController::class, 'googleCallback'])->name('api.meetings.google.callback');
    Route::post('meetings/google/disconnect', [MeetingController::class, 'googleDisconnect'])->name('api.meetings.google.disconnect');
    Route::get('meetings/google/events', [MeetingController::class, 'googleEvents'])->name('api.meetings.google.events');
    
    // Meeting sync routes
    Route::post('meetings/{id}/sync-to-google', [MeetingController::class, 'syncToGoogle'])->name('api.meetings.sync_to_google');
    Route::post('meetings/{id}/remove-from-google', [MeetingController::class, 'removeFromGoogle'])->name('api.meetings.remove_from_google');

    Route::apiResource('tasks', TaskController::class)->names([
        'index' => 'api.tasks.index',
        'store' => 'api.tasks.store',
        'show' => 'api.tasks.show',
        'update' => 'api.tasks.update',
        'destroy' => 'api.tasks.destroy',
    ]);

    Route::apiResource('invoices', InvoiceController::class)->names([
        'index' => 'api.invoices.index',
        'store' => 'api.invoices.store',
        'show' => 'api.invoices.show',
        'update' => 'api.invoices.update',
        'destroy' => 'api.invoices.destroy',
    ]);
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('api.invoices.status');

    Route::apiResource('customers', CustomerController::class)->names([
        'index' => 'api.customers.index',
        'store' => 'api.customers.store',
        'show' => 'api.customers.show',
        'update' => 'api.customers.update',
        'destroy' => 'api.customers.destroy',
    ]);

    Route::apiResource('services', ServiceController::class)->names([
        'index' => 'api.services.index',
        'store' => 'api.services.store',
        'show' => 'api.services.show',
        'update' => 'api.services.update',
        'destroy' => 'api.services.destroy',
    ]);

    Route::apiResource('tickets', SupportTicketController::class)->names([
        'index' => 'api.tickets.index',
        'store' => 'api.tickets.store',
        'show' => 'api.tickets.show',
        'update' => 'api.tickets.update',
        'destroy' => 'api.tickets.destroy',
    ]);
    Route::post('tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('api.tickets.reply');
    Route::patch('tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('api.tickets.status');

    Route::apiResource('pipelines', PipelineController::class)->names([
        'index' => 'api.pipelines.index',
        'store' => 'api.pipelines.store',
        'show' => 'api.pipelines.show',
        'update' => 'api.pipelines.update',
        'destroy' => 'api.pipelines.destroy',
    ]);

    Route::apiResource('products/categories', ApiProductCategoryController::class)->names([
        'index' => 'api.products.categories.index',
        'store' => 'api.products.categories.store',
        'show' => 'api.products.categories.show',
        'update' => 'api.products.categories.update',
        'destroy' => 'api.products.categories.destroy',
    ]);
    Route::patch('products/categories/{category}/toggle-status', [ApiProductCategoryController::class, 'toggleStatus'])->name('api.products.categories.toggleStatus');

    Route::apiResource('products', ProductController::class)->names([
        'index' => 'api.products.index',
        'store' => 'api.products.store',
        'show' => 'api.products.show',
        'update' => 'api.products.update',
        'destroy' => 'api.products.destroy',
    ]);
    Route::post('products/import', [ProductController::class, 'import'])->name('api.products.import');
    // Route::post('products/categories', [ApiProductCategoryController::class, 'store'])->name('api.products.categories.store');

    Route::apiResource('documents', DocumentController::class)->names([
        'index' => 'api.documents.index',
        'store' => 'api.documents.store',
        'show' => 'api.documents.show',
        'update' => 'api.documents.update',
        'destroy' => 'api.documents.destroy',
    ]);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('api.documents.download');

    Route::apiResource('users', UserController::class)->names([
        'index' => 'api.users.index',
        'store' => 'api.users.store',
        'show' => 'api.users.show',
        'update' => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('api.users.status');
    Route::put('users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('api.users.permissions');
    Route::get('users/search', [UserController::class, 'search'])->name('api.users.search');

    // Dashboard API endpoints
    Route::get('dashboard/stats', [WebDashboardController::class, 'apiStats'])->name('api.dashboard.stats');
    Route::get('dashboard/lead-board', [WebDashboardController::class, 'apiLeadBoard'])->name('api.dashboard.lead_board');
    Route::get('dashboard/tasks-widget', [WebDashboardController::class, 'apiTasksWidget'])->name('api.dashboard.tasks_widget');
    Route::get('dashboard/trend', [WebDashboardController::class, 'apiTrend'])->name('api.dashboard.trend');
    Route::get('dashboard/customer-report', [WebDashboardController::class, 'apiCustomerReport'])->name('api.dashboard.customer_report');
    Route::get('dashboard/deals-widget', [WebDashboardController::class, 'apiDealsWidget'])->name('api.dashboard.deals_widget');
    Route::get('dashboard/upcoming-reminders', [WebDashboardController::class, 'apiUpcomingReminders'])->name('api.dashboard.upcoming_reminders');

    // Status History API
    Route::post('status-history', [ApiStatusHistoryController::class, 'store'])->name('api.status_history.store');

    // Masters API
    Route::apiResource('masters/stages', ApiStageController::class)->names([
        'index' => 'api.masters.stages.index',
        'store' => 'api.masters.stages.store',
        'show' => 'api.masters.stages.show',
        'update' => 'api.masters.stages.update',
        'destroy' => 'api.masters.stages.destroy',
    ]);
    // Route::get('masters/stages', [ApiStageController::class, 'index'])->name('api.masters.stages.index');

    // Customers search
    Route::get('customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
    Route::post('customers/import', [CustomerController::class, 'import'])->name('api.customers.import');

    // Test endpoint - PROTECTED
    Route::get('test/auth', function (Request $request) {
        return response()->json([
            'authenticated' => auth()->check(),
            'user' => auth()->user() ? [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ] : null,
            'token' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : 'no bearer token',
            'cookie' => $request->cookie('crm_auth_token') ? substr($request->cookie('crm_auth_token'), 0, 20) . '...' : 'no cookie',
        ]);
    })->name('api.test.auth');

    // Calendar events
    Route::get('calendar/events', [CalendarController::class, 'apiEvents'])->name('api.calendar.events');

    // Follow-ups activities
    Route::get('leads/{id}/activities', [FollowUpController::class, 'apiByLead'])->name('api.leads.activities');

    // WhatsApp Lead Management API
    Route::prefix('whatsapp')->middleware('whatsapp.enabled')->group(function () {
        Route::get('leads', [LeadApiController::class, 'index'])->name('api.whatsapp.leads.index');
        Route::post('leads', [LeadApiController::class, 'store'])->name('api.whatsapp.leads.store');
        Route::get('leads/{lead}', [LeadApiController::class, 'show'])->name('api.whatsapp.leads.show');
        Route::put('leads/{lead}', [LeadApiController::class, 'update'])->name('api.whatsapp.leads.update');
        Route::delete('leads/{lead}', [LeadApiController::class, 'destroy'])->name('api.whatsapp.leads.destroy');
        Route::post('leads/{lead}/notes', [LeadApiController::class, 'addNote'])->name('api.whatsapp.leads.notes');
        Route::post('leads/{lead}/convert', [LeadApiController::class, 'convertToCustomer'])->name('api.whatsapp.leads.convert');
        Route::get('leads/stats', [LeadApiController::class, 'stats'])->name('api.whatsapp.leads.stats');
        Route::get('leads/by-stage', [LeadApiController::class, 'byStage'])->name('api.whatsapp.leads.by-stage');

        // WhatsApp Conversations API
        Route::get('conversations', [WhatsAppApiController::class, 'conversations'])->name('api.whatsapp.conversations.index');
        Route::get('conversations/{conversation}', [WhatsAppApiController::class, 'conversation'])->name('api.whatsapp.conversations.show');
        Route::post('conversations/{conversation}/assign', [WhatsAppApiController::class, 'assign'])->name('api.whatsapp.conversations.assign');
        Route::patch('conversations/{conversation}/status', [WhatsAppApiController::class, 'updateStatus'])->name('api.whatsapp.conversations.status');
        Route::post('conversations/{conversation}/read', [WhatsAppApiController::class, 'markAsRead'])->name('api.whatsapp.conversations.read');
        Route::get('conversations/{conversation}/messages', [WhatsAppApiController::class, 'messages'])->name('api.whatsapp.conversations.messages');

        // WhatsApp Messaging API
        Route::post('send-message', [WhatsAppApiController::class, 'sendMessage'])->name('api.whatsapp.send-message');
        Route::post('send-media', [WhatsAppApiController::class, 'sendMedia'])->name('api.whatsapp.send-media');

        // WhatsApp Automation API
        Route::get('automation', [AutomationApiController::class, 'index'])->name('api.whatsapp.automation.index');
        Route::post('automation', [AutomationApiController::class, 'store'])->name('api.whatsapp.automation.store');
        Route::get('automation/{rule}', [AutomationApiController::class, 'show'])->name('api.whatsapp.automation.show');
        Route::put('automation/{rule}', [AutomationApiController::class, 'update'])->name('api.whatsapp.automation.update');
        Route::delete('automation/{rule}', [AutomationApiController::class, 'destroy'])->name('api.whatsapp.automation.destroy');
        Route::patch('automation/{rule}/toggle', [AutomationApiController::class, 'toggle'])->name('api.whatsapp.automation.toggle');

        // WhatsApp Campaign API
        Route::get('campaigns', [CampaignApiController::class, 'index'])->name('api.whatsapp.campaigns.index');
        Route::post('campaigns', [CampaignApiController::class, 'store'])->name('api.whatsapp.campaigns.store');
        Route::get('campaigns/{campaign}', [CampaignApiController::class, 'show'])->name('api.whatsapp.campaigns.show');
        Route::put('campaigns/{campaign}', [CampaignApiController::class, 'update'])->name('api.whatsapp.campaigns.update');
        Route::delete('campaigns/{campaign}', [CampaignApiController::class, 'destroy'])->name('api.whatsapp.campaigns.destroy');
        Route::post('campaigns/{campaign}/launch', [CampaignApiController::class, 'launch'])->name('api.whatsapp.campaigns.launch');
        Route::get('campaigns/{campaign}/stats', [CampaignApiController::class, 'stats'])->name('api.whatsapp.campaigns.stats');
    });
});
