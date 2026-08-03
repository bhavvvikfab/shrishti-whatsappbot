<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserLogController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\LeadSourceController;
use App\Http\Controllers\LeadStageController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TravelTypeController;
use App\Http\Controllers\RoomCategoryController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\TransportTypeController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InlineActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TourPackageController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\WhatsappConfigController;
use App\Http\Controllers\WhatsAppChatController;
use App\Http\Controllers\DefaultEmailTemplateController;
use App\Http\Controllers\EmailMarketingTemplateController;
use App\Http\Controllers\SmsMarketingController;
use App\Http\Controllers\FacebookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Custom CRM routes with a bespoke login flow.
|
*/

Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/profile/company-logo-image', [ProfileController::class, 'companyLogoImage'])->name('profile.company_logo.image');
// WhatsApp webhook routes live in routes/whatsapp-webhook.php (no web/CSRF middleware).

// Debug route to check auth
Route::get('/debug-auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user(),
        'cookie' => request()->cookie('crm_auth_token') ? 'present' : 'missing',
        'bearer' => request()->bearerToken() ? 'present' : 'missing',
    ]);
});

// Google Calendar OAuth routes - must be outside auth middleware
Route::get('/auth/google', [MeetingController::class, 'redirectToGoogle'])->name('google.auth');
Route::get('/auth/google/callback', [MeetingController::class, 'handleGoogleCallback'])->name('google.callback');
Route::get('/google/calendar/callback', [MeetingController::class, 'handleGoogleCallback']); // Alias for user's configured URI

// Facebook OAuth callback
Route::get('/auth/facebook/callback', [FacebookController::class, 'callback'])->name('facebook.callback');


Route::middleware('auth')->group(function () {
    Route::redirect('/sms-marketing', '/marketing/sms-marketing');
    Route::redirect('/sms-marketing/logs', '/marketing/sms-marketing/logs');
    Route::redirect('/sms-marketing/templates/create', '/marketing/sms-marketing/templates/create');
    Route::redirect('/sms-marketing/templates/{sms_template}/edit', '/marketing/sms-marketing/templates/{sms_template}/edit');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Search route
    Route::get('/global-search', [\App\Http\Controllers\SearchController::class, 'search'])->name('global.search');

    Route::get('/user-logs', [UserLogController::class, 'index'])->middleware('main_admin')->name('user-logs.index');
    Route::delete('/user-logs/{notification}', [UserLogController::class, 'destroy'])->middleware('main_admin')->name('user-logs.destroy');
    Route::delete('/user-logs', [UserLogController::class, 'destroyAll'])->middleware('main_admin')->name('user-logs.destroy_all');
    Route::get('/api/user-logs', [UserLogController::class, 'apiIndex'])->middleware('main_admin')->name('api.user-logs.index');
    Route::get('/api/user-logs/{notification}', [UserLogController::class, 'apiShow'])->middleware('main_admin')->name('api.user-logs.show');
    Route::delete('/api/user-logs/{notification}', [UserLogController::class, 'apiDestroy'])->middleware('main_admin')->name('api.user-logs.destroy');
    Route::delete('/api/user-logs', [UserLogController::class, 'apiDestroyAll'])->middleware('main_admin')->name('api.user-logs.destroy_all');

    // Notifications & Push Subscriptions
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/list', [NotificationController::class, 'index'])->name('list');
        Route::get('/poll', [NotificationController::class, 'poll'])->name('poll');
        Route::delete('/delete-all', [NotificationController::class, 'deleteAll'])->name('delete-all');
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::delete('/{id}', [NotificationController::class, 'deleteNotification'])->name('delete');
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        // Push subscription endpoints temporarily disabled (methods commented out).
        // Route::post('/subscribe', [NotificationController::class, 'subscribe'])->name('subscribe');
        // Route::get('/vapid-key', [NotificationController::class, 'vapidKey'])->name('vapid-key');
    });

    Route::get('/api/dashboard/stats', [DashboardController::class, 'apiStats'])->name('api.dashboard.stats');
    Route::get('/api/dashboard/lead-board', [DashboardController::class, 'apiLeadBoard'])->name('api.dashboard.lead_board');
    Route::get('/api/dashboard/tasks-widget', [DashboardController::class, 'apiTasksWidget'])->name('api.dashboard.tasks_widget');
    Route::get('/api/dashboard/trend', [DashboardController::class, 'apiTrend'])->name('api.dashboard.trend');
    Route::get('/api/dashboard/customer-report', [DashboardController::class, 'apiCustomerReport'])->name('api.dashboard.customer_report');
    Route::get('/api/dashboard/deals-widget', [DashboardController::class, 'apiDealsWidget'])->name('api.dashboard.deals_widget');
    Route::get('/api/dashboard/upcoming-reminders', [DashboardController::class, 'apiUpcomingReminders'])->name('api.dashboard.upcoming_reminders');
    Route::get('/api/dashboard/inactive-leads', [DashboardController::class, 'apiInactiveLeads'])->name('api.dashboard.inactive_leads');

    Route::prefix('inline-activities/{type}/{id}')->name('inline-activities.')->group(function () {
        Route::post('/notes', [InlineActivityController::class, 'storeNote'])->name('notes.store');
        Route::post('/follow-ups', [InlineActivityController::class, 'storeFollowUp'])->name('followups.store');
        Route::post('/tasks', [InlineActivityController::class, 'storeTask'])->name('tasks.store');
        Route::post('/meetings', [InlineActivityController::class, 'storeMeeting'])->name('meetings.store');
    });

    // Core CRM web routes (view only)
    // Route::resource('leads', LeadController::class)->except(['store', 'update', 'destroy'])->middleware('matrix_permission:view_leads');
    Route::get('/leads', [LeadController::class, 'index'])->middleware('matrix_permission:view_leads')->name('leads.index');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->middleware('matrix_permission:edit_leads')->name('leads.edit');
    Route::get('/leads/create', [LeadController::class, 'create'])->middleware('matrix_permission:create_leads')->name('leads.create');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->middleware('matrix_permission:view_leads')->name('leads.show');

    Route::get('leads-export', [LeadController::class, 'export'])->middleware('matrix_permission:view_leads')->name('leads.export');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convertToCustomer'])->middleware('matrix_permission:edit_leads')->name('leads.convert');
    Route::get('leads/{lead}/image', [LeadController::class, 'image'])->middleware('matrix_permission:view_leads')->name('leads.image');
    Route::get('customers/{customer}/image', [CustomerController::class, 'image'])->middleware('matrix_permission:view_customers')->name('customers.image');

    Route::get('/deals', [DealController::class, 'index'])->middleware('matrix_permission:view_deals')->name('deals.index');
    Route::get('/deals/create', [DealController::class, 'create'])->middleware('matrix_permission:create_deals')->name('deals.create');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->middleware('matrix_permission:view_deals')->name('deals.show');
    Route::get('/deals/{deal}/edit', [DealController::class, 'edit'])->middleware('matrix_permission:edit_deals')->name('deals.edit');
    Route::get('deals-export', [DealController::class, 'export'])->middleware('matrix_permission:view_deals')->name('deals.export');
    Route::get('/pipeline', [PipelineController::class, 'index'])->middleware('matrix_permission:view_pipeline')->name('pipeline.index');
    Route::get('pipeline-export', [PipelineController::class, 'export'])->middleware('matrix_permission:view_pipeline')->name('pipeline.export');
    Route::get('/pipeline/create', [DealController::class, 'pipelineCreate'])->middleware('matrix_permission:create_pipeline')->name('pipeline.create');
    Route::get('/pipeline/{pipeline}/edit', [PipelineController::class, 'edit'])->middleware('matrix_permission:edit_pipeline')->name('pipeline.edit');
    Route::get('/pipeline/{pipeline}', [PipelineController::class, 'show'])->middleware('matrix_permission:view_pipeline')->name('pipeline.show');

    // Projects web routes
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('matrix_permission:view_projects')->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('matrix_permission:create_projects')->name('projects.create');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('matrix_permission:view_projects')->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('matrix_permission:edit_projects')->name('projects.edit');
    Route::get('projects-export', [ProjectController::class, 'export'])->middleware('matrix_permission:view_projects')->name('projects.export');

    // Meetings web routes
    Route::get('/meetings', [MeetingController::class, 'index'])->middleware('matrix_permission:view_meetings')->name('meetings.index');
    Route::get('meetings-export', [MeetingController::class, 'export'])->middleware('matrix_permission:view_meetings')->name('meetings.export');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->middleware('matrix_permission:create_meetings')->name('meetings.create');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->middleware('matrix_permission:view_meetings')->name('meetings.show');
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->middleware('matrix_permission:edit_meetings')->name('meetings.edit');

    // Tasks web routes
    Route::get('/tasks', [TaskController::class, 'index'])->middleware('matrix_permission:view_tasks')->name('tasks.index');
    Route::get('/tasks/create', [TaskController::class, 'create'])->middleware('matrix_permission:create_tasks')->name('tasks.create');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->middleware('matrix_permission:view_tasks')->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->middleware('matrix_permission:edit_tasks')->name('tasks.edit');
    Route::get('tasks-export', [TaskController::class, 'export'])->middleware('matrix_permission:view_tasks')->name('tasks.export');

    // Settings & Dynamic Form Builder
    Route::group(['prefix' => 'settings', 'as' => 'settings.'], function () {
        Route::resource('custom-fields', CustomFieldController::class);
    });

    Route::get('/follow-ups', [FollowUpController::class, 'index'])->middleware('matrix_permission:view_followups')->name('followups.index');
    Route::get('/follow-ups/create', [FollowUpController::class, 'create'])->middleware('matrix_permission:create_followups')->name('followups.create');
    Route::get('/follow-ups/{followup}', [FollowUpController::class, 'show'])->middleware('matrix_permission:view_followups')->name('followups.show');
    Route::get('/follow-ups/{followup}/edit', [FollowUpController::class, 'edit'])->middleware('matrix_permission:edit_followups')->name('followups.edit');
    Route::delete('/follow-ups/{followup}', [FollowUpController::class, 'destroy'])->middleware('matrix_permission:delete_followups')->name('followups.destroy');
    Route::get('/follow-ups-export', [FollowUpController::class, 'export'])->middleware('matrix_permission:view_followups')->name('followups.export');
    Route::post('follow-ups/{followup}/toggle', [FollowUpController::class, 'toggle'])->middleware('matrix_permission:edit_followups')->name('followups.toggle');

    // Tour & Booking web routes
    Route::resource('packages', TourPackageController::class);
    Route::resource('quotations', QuotationController::class);
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convertToBooking'])->name('quotations.convert');
    Route::resource('bookings', BookingController::class);
    Route::post('bookings/{booking}/amend', [BookingController::class, 'amend'])->name('bookings.amend');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Invoices web routes
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('matrix_permission:view_invoices')->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('matrix_permission:create_invoices')->name('invoices.create');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('matrix_permission:view_invoices')->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('matrix_permission:edit_invoices')->name('invoices.edit');
    Route::post('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->middleware('matrix_permission:edit_invoices')->name('invoices.status');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('matrix_permission:view_invoices')->name('invoices.pdf');
    Route::get('invoices-export', [InvoiceController::class, 'export'])->middleware('matrix_permission:view_invoices')->name('invoices.export');
    Route::get('bookings/{booking}/invoice/create', [InvoiceController::class, 'createByBooking'])->name('bookings.invoice.create');

    // Travel Operations & Financials
    Route::get('packages/{package}/itinerary', [ItineraryController::class, 'editByPackage'])->name('packages.itinerary');
    Route::post('packages/{package}/itinerary', [ItineraryController::class, 'updateByPackage'])->name('packages.itinerary.update');
    Route::get('quotations/{quotation}/itinerary', [ItineraryController::class, 'editByQuotation'])->name('quotations.itinerary');
    Route::post('quotations/{quotation}/itinerary', [ItineraryController::class, 'updateByQuotation'])->name('quotations.itinerary.update');
    Route::get('bookings/{booking}/itinerary', [ItineraryController::class, 'editByBooking'])->name('bookings.itinerary');
    Route::post('bookings/{booking}/itinerary', [ItineraryController::class, 'updateByBooking'])->name('bookings.itinerary.update');
    Route::post('checklists/{checklist}/toggle', [BookingController::class, 'toggleChecklist'])->name('checklists.toggle');

    // Refunds & Cancellations
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('bookings/{booking}/refunds', [BookingController::class, 'refunds'])->name('bookings.refunds');
    Route::post('bookings/{booking}/refunds', [BookingController::class, 'storeRefund'])->name('bookings.refunds.store');

    // Supplier Payments & Costs
    Route::get('suppliers/{supplier}/payables', [SupplierController::class, 'payables'])->name('suppliers.payables');
    Route::post('payables/{payable}/payments', [SupplierController::class, 'storePayment'])->name('payables.payments.store');
    Route::get('bookings/{booking}/costs', [BookingController::class, 'costs'])->name('bookings.costs');
    Route::post('bookings/{booking}/costs', [BookingController::class, 'storeCost'])->name('bookings.costs.store');
    Route::delete('costs/{payable}', [BookingController::class, 'destroyCost'])->name('bookings.costs.destroy');
    Route::get('bookings/{booking}/voucher', [BookingController::class, 'voucher'])->name('bookings.voucher');

    Route::resource('payments', PaymentController::class)->only(['index', 'store', 'destroy']);
    Route::get('invoices/{invoice}/payments', [PaymentController::class, 'indexByInvoice'])->name('invoices.payments');

    // Support Tickets web routes
    Route::get('/tickets', [SupportTicketController::class, 'index'])->middleware('matrix_permission:view_tickets')->name('tickets.index');
    Route::get('/tickets/create', [SupportTicketController::class, 'create'])->middleware('matrix_permission:create_tickets')->name('tickets.create');
    Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->middleware('matrix_permission:view_tickets')->name('tickets.show');
    Route::get('/tickets/{ticket}/edit', [SupportTicketController::class, 'edit'])->middleware('matrix_permission:edit_tickets')->name('tickets.edit');
    Route::get('tickets-export', [SupportTicketController::class, 'export'])->middleware('matrix_permission:view_tickets')->name('tickets.export');
    Route::get('support-export', [SupportTicketController::class, 'export'])->middleware('matrix_permission:view_tickets')->name('supportticket.export');

    // Products web routes
    Route::get('products-export', [ProductController::class, 'export'])->middleware('matrix_permission:view_products')->name('products.export');
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/categories', [ProductCategoryController::class, 'index'])->middleware('matrix_permission:view_products')->name('categories.index');
    });
    Route::get('products/{product}/image', [ProductController::class, 'image'])->middleware('matrix_permission:view_products')->name('products.image');
    Route::get('/products', [ProductController::class, 'index'])->middleware('matrix_permission:view_products')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('matrix_permission:create_products')->name('products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('matrix_permission:view_products')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('matrix_permission:edit_products')->name('products.edit');

    // Services web routes
    Route::get('services-export', [ServiceController::class, 'export'])->middleware('matrix_permission:view_services')->name('services.export');
    Route::get('/services', [ServiceController::class, 'index'])->middleware('matrix_permission:view_services')->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->middleware('matrix_permission:create_services')->name('services.create');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->middleware('matrix_permission:view_services')->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->middleware('matrix_permission:edit_services')->name('services.edit');

    // Documents web routes
    Route::resource('documents', DocumentController::class)->only(['index', 'create', 'edit']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/google/disconnect', [ProfileController::class, 'disconnectGoogle'])->name('profile.google.disconnect');

    // Users & Roles web routes
    Route::resource('users', UserController::class)->except(['store', 'update', 'destroy'])->middleware('main_admin');
    Route::get('users/{user}/image', [UserController::class, 'image'])->middleware(['auth', 'auth.api'])->name('users.image');
    Route::post('/users/import', [UserController::class, 'import'])->middleware('main_admin')->name('users.import');
    Route::get('users-export', [UserController::class, 'export'])->middleware('main_admin')->name('users.export');
    Route::resource('roles', RoleController::class);

    // Masters web routes
    Route::group(['prefix' => 'masters', 'as' => 'masters.'], function () {
        Route::resource('countries', CountryController::class)->except(['show']);
        Route::resource('cities', CityController::class)->except(['show']);
        Route::resource('lead-sources', LeadSourceController::class)->names('lead_sources')->except(['show']);
        Route::get('stages', [StageController::class, 'index'])->middleware('matrix_permission:view_stages')->name('stages.index');
        Route::resource('travel-types', TravelTypeController::class)->names('travel_types')->except(['show']);
        Route::resource('room-categories', RoomCategoryController::class)->names('room_categories')->except(['show']);
        Route::resource('transport-types', TransportTypeController::class)->names('transport_types')->except(['show']);
        Route::resource('currencies', CurrencyController::class)->names('currencies')->except(['show']);
        Route::resource('suppliers', SupplierController::class)->names('suppliers')->except(['show']);
        Route::resource('hotels', HotelController::class)->names('hotels')->except(['show']);
        Route::resource('agents', AgentController::class)->names('agents')->except(['show']);

        // Route::resource('customers', CustomerController::class)->names('customers')->except(['store', 'update', 'destroy']);
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('matrix_permission:view_customers')->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('matrix_permission:create_customers')->name('customers.create');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('matrix_permission:view_customers')->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('matrix_permission:edit_customers')->name('customers.edit');


        Route::get('customers-export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('cities-by-country/{country}', [CityController::class, 'apiByCountry'])->name('cities.by_country');
        Route::resource('default-email-templates', DefaultEmailTemplateController::class)->names('default_email_templates');
        Route::post('default-email-templates/{email_template}/default', [DefaultEmailTemplateController::class, 'setDefault'])
            ->name('default_email_templates.set_default');
        Route::get('default-email-templates-list', [DefaultEmailTemplateController::class, 'apiIndex'])
            ->name('default_email_templates.api_index');
    });

    // Settings web routes
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Facebook Integration Routes
    Route::get('/settings/facebook', [FacebookController::class, 'index'])->name('facebook.index');
    Route::get('/settings/facebook/connect', [FacebookController::class, 'connect'])->name('facebook.connect');
    Route::delete('/settings/facebook/{id}', [FacebookController::class, 'disconnect'])->name('facebook.disconnect');
    Route::post('/settings/facebook/{id}/sync', [FacebookController::class, 'syncAll'])->name('facebook.sync');
    Route::post('/settings/facebook/page/{id}/subscription', [FacebookController::class, 'togglePageSubscription'])->name('facebook.page.subscription');

    // Reports & Analytics web routes
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('/', [ReportController::class, 'index'])->middleware('matrix_permission:view_reports')->name('index');
        Route::get('/customers_report', [ReportController::class, 'customersReport'])->middleware('matrix_permission:view_reports')->name('customers');
        Route::get('/leads_report', [ReportController::class, 'leadsReport'])->middleware('matrix_permission:view_reports')->name('leads');
        Route::get('/leads_report/export', [ReportController::class, 'leadsExport'])->middleware('matrix_permission:view_reports')->name('leads.export');
        Route::get('/deals_report', [ReportController::class, 'dealsReport'])->middleware('matrix_permission:view_reports')->name('deals');
        Route::get('/deals_report/export', [ReportController::class, 'dealsExport'])->middleware('matrix_permission:view_reports')->name('deals.export');
        Route::get('/projects_report', [ReportController::class, 'projectsReport'])->middleware('matrix_permission:view_reports')->name('projects');
        Route::get('/projects_report/export', [ReportController::class, 'projectsExport'])->middleware('matrix_permission:view_reports')->name('projects.export');
        Route::get('/tasks_report', [ReportController::class, 'tasksReport'])->middleware('matrix_permission:view_reports')->name('tasks');
        Route::get('/tasks_report/export', [ReportController::class, 'tasksExport'])->middleware('matrix_permission:view_reports')->name('tasks.export');
        Route::get('/followups_report', [ReportController::class, 'followupsReport'])->middleware('matrix_permission:view_reports')->name('followups');
        Route::get('/followups_report/export', [ReportController::class, 'followupsExport'])->middleware('matrix_permission:view_reports')->name('followups.export');
        Route::get('/profit-loss', [ReportController::class, 'profitAndLoss'])->middleware('matrix_permission:view_reports')->name('profit_loss');
        Route::get('/sales', [ReportController::class, 'salesPerformance'])->middleware('matrix_permission:view_reports')->name('sales');
        Route::get('/pending', [ReportController::class, 'pendingAccounts'])->middleware('matrix_permission:view_reports')->name('pending');
    });

    // Operations web routes
    Route::group(['prefix' => 'operations', 'as' => 'operations.'], function () {
        Route::get('/rooming-list', [OperationsController::class, 'roomingList'])->name('rooming_list');
        Route::get('/driver-sheet', [OperationsController::class, 'driverSheet'])->name('driver_sheet');
    });

    // Marketing Automation web routes
    Route::group(['prefix' => 'marketing', 'as' => 'marketing.'], function () {
        Route::get('/dashboard', [MarketingController::class, 'dashboard'])->middleware('matrix_permission:view_email')->name('dashboard');
        Route::get('/templates', [MarketingController::class, 'templatesIndex'])->middleware('matrix_permission:view_email')->name('templates.index');
        Route::get('/templates/create', [MarketingController::class, 'templatesCreate'])->middleware('matrix_permission:create_email')->name('templates.create');
        Route::post('/templates', [MarketingController::class, 'templatesStore'])->middleware('matrix_permission:create_email')->name('templates.store');
        Route::get('/templates/{template}', [MarketingController::class, 'templatesShow'])->middleware('matrix_permission:view_email')->name('templates.show');
        Route::get('/templates/{template}/edit', [MarketingController::class, 'templatesEdit'])->middleware('matrix_permission:edit_email')->name('templates.edit');
        Route::put('/templates/{template}', [MarketingController::class, 'templatesUpdate'])->middleware('matrix_permission:edit_email')->name('templates.update');
        Route::delete('/templates/{template}', [MarketingController::class, 'templatesDestroy'])->middleware('matrix_permission:delete_email')->name('templates.destroy');
        Route::post('/templates/bulk-send', [MarketingController::class, 'bulkSendMail'])->name('templates.bulk_send');

        Route::get('/campaigns', [MarketingController::class, 'campaignsIndex'])->name('campaigns.index');
        Route::get('/campaigns/create', [MarketingController::class, 'campaignsCreate'])->name('campaigns.create');
        Route::post('/campaigns', [MarketingController::class, 'campaignsStore'])->name('campaigns.store');
        Route::post('/campaigns/{campaign}/send', [MarketingController::class, 'sendCampaign'])->name('campaigns.send');
        Route::delete('/campaigns/{campaign}', [MarketingController::class, 'campaignsDestroy'])->name('campaigns.destroy');

        Route::resource('email-marketing-templates', EmailMarketingTemplateController::class)
            ->names('email_marketing_templates');

        // SMS Marketing
        Route::get('/sms-marketing', [SmsMarketingController::class, 'index'])->name('sms_marketing.index');
        Route::get('/sms-marketing/logs', [SmsMarketingController::class, 'logs'])->name('sms_marketing.logs');
        Route::get('/sms-marketing/templates/create', [SmsMarketingController::class, 'createTemplate'])->name('sms_marketing.templates.create');
        Route::post('/sms-marketing/templates', [SmsMarketingController::class, 'storeTemplate'])->name('sms_marketing.templates.store');
        Route::get('/sms-marketing/templates/{sms_template}/edit', [SmsMarketingController::class, 'editTemplate'])->name('sms_marketing.templates.edit');
        Route::put('/sms-marketing/templates/{sms_template}', [SmsMarketingController::class, 'updateTemplate'])->name('sms_marketing.templates.update');
        Route::delete('/sms-marketing/templates/{sms_template}', [SmsMarketingController::class, 'destroyTemplate'])->name('sms_marketing.templates.destroy');
        Route::post('/sms-marketing/save-credentials', [SmsMarketingController::class, 'saveCredentials'])->name('sms_marketing.save_credentials');
        Route::post('/sms-marketing/send-sms', [SmsMarketingController::class, 'sendSms'])->name('sms_marketing.send_sms');
        Route::delete('/sms-marketing/logs/{sms_log}', [SmsMarketingController::class, 'destroy'])->name('sms_marketing.logs.destroy');
    });

    // ─── WhatsApp CRM System ────────────────────────────────────────────────────
    Route::prefix('whatsapp')->name('whatsapp.')->middleware('whatsapp.enabled')->group(function () {

        // Chat Inbox
        Route::get('/inbox', [\App\Http\Controllers\WhatsappInboxController::class, 'inbox'])->name('inbox');
        Route::get('/inbox/search', [\App\Http\Controllers\WhatsappInboxController::class, 'searchInbox'])->name('inbox.search');
        Route::get('/inbox/conversations', [\App\Http\Controllers\WhatsappInboxController::class, 'conversationList'])->name('conversations.list');
        Route::get('/inbox/media/{filename}', [\App\Http\Controllers\WhatsappInboxController::class, 'media'])->name('media');
        Route::get('/inbox/meta-media/{message}', [\App\Http\Controllers\WhatsappInboxController::class, 'metaMedia'])->name('meta_media');
        Route::get('/inbox/{conversation}', [\App\Http\Controllers\WhatsappInboxController::class, 'conversation'])->name('conversation');
        Route::post('/inbox/{conversation}/send', [\App\Http\Controllers\WhatsappInboxController::class, 'sendMessage'])->name('conversation.send');
        Route::post('/inbox/{conversation}/send-media', [\App\Http\Controllers\WhatsappInboxController::class, 'sendMedia'])->name('conversation.send_media');
        Route::post('/inbox/{conversation}/send-location', [\App\Http\Controllers\WhatsappInboxController::class, 'sendLocation'])->name('conversation.send_location');
        Route::get('/inbox/{conversation}/messages', [\App\Http\Controllers\WhatsappInboxController::class, 'getMessages'])->name('conversation.messages');
        Route::get('/inbox/{conversation}/message-search', [\App\Http\Controllers\WhatsappInboxController::class, 'searchConversationMessages'])->name('conversation.message_search');
        Route::delete('/inbox/{conversation}/messages/{message}', [\App\Http\Controllers\WhatsappInboxController::class, 'deleteMessage'])->name('conversation.delete_message');
        Route::post('/inbox/{conversation}/messages/{message}/react', [\App\Http\Controllers\WhatsappInboxController::class, 'reactMessage'])->name('conversation.react');
        Route::post('/inbox/{conversation}/assign', [\App\Http\Controllers\WhatsappInboxController::class, 'assign'])->name('conversation.assign');
        Route::post('/inbox/{conversation}/status', [\App\Http\Controllers\WhatsappInboxController::class, 'updateStatus'])->name('conversation.status');
        Route::post('/inbox/{conversation}/tag', [\App\Http\Controllers\WhatsappInboxController::class, 'toggleTag'])->name('conversation.tag');
        Route::post('/inbox/{conversation}/pin/{message}', [\App\Http\Controllers\WhatsappInboxController::class, 'pinMessage'])->name('conversation.pin');
        Route::post('/inbox/{conversation}/unpin', [\App\Http\Controllers\WhatsappInboxController::class, 'unpinMessage'])->name('conversation.unpin');
        Route::post('/inbox/{conversation}/ai-reply', [\App\Http\Controllers\WhatsappInboxController::class, 'setAiReplyPreference'])->name('conversation.ai_reply');
        Route::post('/inbox/{conversation}/create-lead', [\App\Http\Controllers\WhatsappInboxController::class, 'createLead'])->name('conversation.create_lead');

        // Automation Rules
        Route::resource('automation', \App\Http\Controllers\WhatsappAutomationController::class)->names('automation');
        Route::post('/automation/{rule}/toggle', [\App\Http\Controllers\WhatsappAutomationController::class, 'toggleStatus'])->name('automation.toggle');

        // Campaigns
        Route::resource('campaigns', \App\Http\Controllers\WhatsappCampaignController::class)->names('campaigns');
        Route::post('/campaigns/{campaign}/launch', [\App\Http\Controllers\WhatsappCampaignController::class, 'launch'])->name('campaigns.launch');
        Route::post('/campaigns/{campaign}/cancel', [\App\Http\Controllers\WhatsappCampaignController::class, 'cancel'])->name('campaigns.cancel');

        // Followups
        Route::get('/followups', [\App\Http\Controllers\WhatsappFollowupController::class, 'index'])->name('followups.index');
        Route::post('/followups', [\App\Http\Controllers\WhatsappFollowupController::class, 'store'])->name('followups.store');
        Route::put('/followups/{followup}', [\App\Http\Controllers\WhatsappFollowupController::class, 'update'])->name('followups.update');
        Route::post('/followups/{followup}/complete', [\App\Http\Controllers\WhatsappFollowupController::class, 'complete'])->name('followups.complete');
        Route::delete('/followups/{followup}', [\App\Http\Controllers\WhatsappFollowupController::class, 'destroy'])->name('followups.destroy');

        // Analytics
        Route::get('/analytics', [\App\Http\Controllers\WhatsappAnalyticsController::class, 'dashboard'])->name('analytics');
        Route::get('/analytics/chart-data', [\App\Http\Controllers\WhatsappAnalyticsController::class, 'getChartData'])->name('analytics.chart_data');
    });

    // Lead Notes & Tags
    Route::prefix('leads/{lead}')->name('leads.')->group(function () {
        Route::get('/notes', [\App\Http\Controllers\LeadNoteController::class, 'index'])->name('notes.index');
        Route::post('/notes', [\App\Http\Controllers\LeadNoteController::class, 'store'])->name('notes.store');
        Route::delete('/notes/{note}', [\App\Http\Controllers\LeadNoteController::class, 'destroy'])->name('notes.destroy');
        Route::post('/tags/attach', [\App\Http\Controllers\LeadTagController::class, 'attachToLead'])->name('tags.attach');
        Route::delete('/tags/{tag}/detach', [\App\Http\Controllers\LeadTagController::class, 'detachFromLead'])->name('tags.detach');
    });
    Route::get('/lead-tags', [\App\Http\Controllers\LeadTagController::class, 'index'])->name('lead-tags.index');
    Route::post('/lead-tags', [\App\Http\Controllers\LeadTagController::class, 'store'])->name('lead-tags.store');
    Route::put('/lead-tags/{tag}', [\App\Http\Controllers\LeadTagController::class, 'update'])->name('lead-tags.update');
    Route::delete('/lead-tags/{tag}', [\App\Http\Controllers\LeadTagController::class, 'destroy'])->name('lead-tags.destroy');
    Route::get('/api/lead-tags', [\App\Http\Controllers\LeadTagController::class, 'list'])->name('api.lead-tags.list');
    // ────────────────────────────────────────────────────────────────────────────

    // WhatsApp configuration web routes
    Route::middleware('whatsapp.enabled')->group(function () {
        Route::get('/whatsapp-config', [WhatsappConfigController::class, 'show'])->name('whatsapp.config.show');
        Route::post('/whatsapp-config', [WhatsappConfigController::class, 'store'])->name('whatsapp.config.store');
        Route::post('/whatsapp-templates/refresh', [WhatsappConfigController::class, 'refreshTemplates'])->name('whatsapp.templates.refresh');
        Route::post('/whatsapp-templates/{template}/module', [WhatsappConfigController::class, 'updateTemplateModule'])->name('whatsapp.templates.update_module');
        Route::post('/whatsapp-templates/{template}/status', [WhatsappConfigController::class, 'updateTemplateStatus'])->name('whatsapp.templates.update_status');
        Route::post('/whatsapp-send', [WhatsappConfigController::class, 'send'])->name('whatsapp.send');
        Route::get('/whatsapp-logs', [WhatsappConfigController::class, 'logs'])->name('whatsapp.logs');
    });

    // AI Chatbot route
    Route::post('/crm-assistant', [ChatbotController::class, 'assistant']);
});
