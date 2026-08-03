<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\LeadObserver;
use App\Observers\StaffObserver;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Deal::observe(\App\Observers\DealObserver::class);
        Customer::observe(CustomerObserver::class);
        Lead::observe(LeadObserver::class);
        User::observe(StaffObserver::class);

        // New Automated Notification Observers
        \App\Models\FollowUp::observe(\App\Observers\FollowUpObserver::class);
        \App\Models\Meeting::observe(\App\Observers\MeetingObserver::class);
        \App\Models\Project::observe(\App\Observers\ProjectObserver::class);
        \App\Models\Task::observe(\App\Observers\TaskObserver::class);
        \App\Models\Stage::observe(\App\Observers\StageObserver::class);
        \App\Models\Pipeline::observe(\App\Observers\PipelineObserver::class);
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\SupportTicket::observe(\App\Observers\SupportTicketObserver::class);
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\ProductCategory::observe(\App\Observers\ProductCategoryObserver::class);
        \App\Models\Service::observe(\App\Observers\ServiceObserver::class);

        // Ensure critical storage directories exist (helps with FTP deployments)
        $directories = [
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
        ];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        // Force HTTPS if not on local development
        if (!app()->runningInConsole()) {
            $host = request()->getHost();
            if (!in_array($host, ['localhost', '127.0.0.1', '::1'])) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // Always generate links from APP_URL so Home Screen /public/ start URLs
        // do not produce /public/public/... asset paths and break mobile CSS.
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
        }

        View::composer(['layouts.app', 'profile.show'], function ($view): void {
            $googleCalendarConnected = false;
            $googleConnectedEmail = null;

            try {
                $googleService = app(GoogleCalendarService::class);
                $googleCalendarConnected = $googleService->isAuthenticated();

                if ($googleCalendarConnected) {
                    $googleConnectedEmail = $googleService->getConnectedEmail();
                }
            } catch (\Throwable $e) {
                $googleCalendarConnected = false;
            }

            $view->with('googleCalendarConnected', $googleCalendarConnected);
            $view->with('googleConnectedEmail', $googleConnectedEmail);
        });
    }
}
