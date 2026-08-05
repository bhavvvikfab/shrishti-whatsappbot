<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#075e54">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Shrishti Trip">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ \App\Support\BusinessProfile::name() }}</title>

    <!-- Google Fonts (Outfit)     -->
    <link rel="icon" type="image/png"
        href="{{ crm_asset('images/crmfavicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180"
        href="{{ crm_asset('images/app-icon-180.png') }}">
    <link rel="manifest"
        href="{{ crm_asset('site.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet"
        href="{{ crm_asset('css/crm-layout.css', filemtime(public_path('css/crm-layout.css'))) }}">
    <link rel="stylesheet"
        href="{{ crm_asset('css/buttons.css', filemtime(public_path('css/buttons.css'))) }}">
    {{-- Critical WhatsApp mobile rule: prevents the desktop empty pane from
         flashing before page-specific SPA styles have been applied. --}}
    <style>
        @media (max-width: 767.98px) {
            .wa-inbox-wrap > .wa-main {
                display: none !important;
            }
        }
    </style>
    @stack('styles')


    @auth
        <link rel="stylesheet"
            href="{{ crm_asset('css/chatbot.css', filemtime(public_path('css/chatbot.css'))) }}">
    @endauth
    <link rel="stylesheet"
        href="{{ crm_asset('css/main.css', filemtime(public_path('css/main.css'))) }}">

    <!-- Theme Management -->
    <script
        src="{{ crm_asset('js/theme.js', filemtime(public_path('js/theme.js'))) }}"></script>
    <script>
        window.CRM_AUTH_TOKEN_KEY = 'crm_auth_token';

        window.crmAuthToken = function () {
            try {
                return localStorage.getItem(window.CRM_AUTH_TOKEN_KEY || 'crm_auth_token') || '';
            } catch (error) {
                return '';
            }
        };

        window.crmApplyAuthHeaders = function (headers) {
            const token = window.crmAuthToken();

            if (!token) {
                return headers;
            }

            if (headers instanceof Headers) {
                if (!headers.has('Authorization')) {
                    headers.set('Authorization', 'Bearer ' + token);
                }

                return headers;
            }

            if (Array.isArray(headers)) {
                const hasAuthorization = headers.some(function (entry) {
                    return Array.isArray(entry) && String(entry[0]).toLowerCase() === 'authorization';
                });

                if (!hasAuthorization) {
                    headers.push(['Authorization', 'Bearer ' + token]);
                }

                return headers;
            }

            const normalized = headers && typeof headers === 'object' ? { ...headers } : {};

            if (!Object.keys(normalized).some(function (key) {
                return key.toLowerCase() === 'authorization';
            })) {
                normalized.Authorization = 'Bearer ' + token;
            }

            return normalized;
        };

        (function () {
            const nativeFetch = window.fetch?.bind(window);

            if (!nativeFetch) {
                return;
            }

            window.fetch = function (input, init) {
                const nextInit = init ? { ...init } : {};

                if (input instanceof Request) {
                    const request = input;
                    const requestHeaders = new Headers(request.headers || {});
                    window.crmApplyAuthHeaders(requestHeaders);

                    return nativeFetch(new Request(request, {
                        headers: requestHeaders,
                    }), nextInit);
                }

                nextInit.headers = window.crmApplyAuthHeaders(nextInit.headers || {});

                return nativeFetch(input, nextInit);
            };
        })();
    </script>
</head>

<body class="{{ trim((request()->routeIs('*.create', '*.edit') ? 'crm-form-page ' : '') . (request()->routeIs('whatsapp.inbox', 'whatsapp.conversation') ? 'wa-whatsapp-page' : '')) }}">
    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"
        style="position: fixed !important; top: 0 !important; right: 0 !important; left: auto !important; z-index: 9999;"></div>
    <div id="app" class="d-flex min-vh-100">
        @auth
            @php
                $authUser = auth()->user();
                $userRoleLabel = $authUser?->isAdmin()
                    ? 'Administrator'
                    : ($authUser?->roles->first()?->name
                        ? \Illuminate\Support\Str::headline($authUser->roles->first()->name)
                        : ($authUser?->job_title ?: 'Staff'));
                $navLinkClasses = function (bool $canAccess, bool $isActive = false) {
                    $classes = 'nav-link ccc ddd';
                    if ($isActive) {
                        $classes .= ' active';
                    }
                    if (!$canAccess) {
                        $classes .= ' crm-nav-disabled';
                    }
                    return $classes;
                };
                $navHref = function (bool $canAccess, string $route) {
                    return $canAccess ? $route : 'javascript:void(0)';
                };
                $canViewCustomers = (bool) $authUser;
                $whatsappModuleEnabled = \App\Models\Setting::isEnabled('whatsapp_module_enabled', true);
                $canViewWhatsapp = $whatsappModuleEnabled && (bool) $authUser?->canUseWhatsappInbox();
            @endphp
            <!-- Sidebar -->
            <aside class="crm-sidebar shadow-sm" id="sidenav-main" style="min-width: 260px">
                <div class="sidenav-header">
                    <a class="navbar-brand m-0 d-flex flex-row align-items-center" href="{{ route('whatsapp.inbox') }}">
                        <span class="sidebar-logo-wrap">
                            <img src="{{ crm_asset('images/logo1.png')}}" class="navbar-brand-img h-100" alt="main_logo">
                        </span>
                    </a>
                </div>

                <div class="sidenav-header" style="padding: 0px 8px;">
                    <div class="profile-card brand-card">
                        <img src="{{ crm_asset('images/crmfavicon.png') }}"
                            class="brand-logo-icon" alt="Fablead Logo">
                        <span class="brand-logo-text capitalize">{{ strtoupper($authUser?->name ?? 'FableadCRM') }}</span>
                    </div>
                </div>

                <nav class="navbar-nav overflow-x-hidden" style="padding: 0px 8px; padding-bottom:25px;">
                    {{-- HIDDEN MODULES START (kept for future use, only WhatsApp is shown)
                    <!-- Dashboard -->
                    <li class="nav-item mt-2">
                        <a class="nav-link ccc ddd @if(request()->routeIs('dashboard')) active @endif"
                            href="{{ route('dashboard') }}">
                            <i class="fa-solid fa-tv me-2 text-primary"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>


                    @if (auth()->user()?->hasMatrixPermission('view_customers'))
                        <!-- Manage Customers -->
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses($canViewCustomers, request()->routeIs('masters.customers.*')) }}"
                                href="{{ $navHref($canViewCustomers, route('masters.customers.index')) }}"
                                @unless($canViewCustomers) aria-disabled="true" tabindex="-1" @endunless>
                                <i class="fa fa-users me-2 text-success"></i>
                                <span>Manage Customers</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_leads'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_leads'), request()->routeIs('leads.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_leads'), route('leads.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_leads')) aria-disabled="true" tabindex="-1"
                                @endunless>
                                <i class="fa fa-bullhorn me-2 text-warning"></i>
                                <span>Manage Leads</span>
                            </a>
                        </li>
                    @endif

                    HIDDEN MODULES END --}}

                    @if ($canViewWhatsapp)
                        <!-- Manage Staff -->
                        @if(auth()->user()?->isAdmin())
                            <li class="nav-item mt-2">
                                <a class="nav-link ccc ddd @if(request()->routeIs('users.*')) active @endif"
                                    href="{{ route('users.index') }}">
                                    <i class="fa fa-users me-2 text-info"></i>
                                    <span>Manage Staff</span>
                                </a>
                            </li>
                        @endif

                        <li class="nav-item mt-2">
                            <a class="nav-link ccc ddd @if(request()->routeIs('whatsapp.inbox') || request()->routeIs('whatsapp.conversation')) active @endif"
                                href="{{ route('whatsapp.inbox') }}">
                                <i class="fab fa-whatsapp me-2 text-success"></i>
                                <span>WhatsApp Inbox</span>
                            </a>
                        </li>
                        @if(auth()->user()?->isAdmin())
                            <li class="nav-item mt-1">
                                <a class="nav-link ccc ddd @if(request()->routeIs('settings.*')) active @endif"
                                    href="{{ route('settings.index') }}#whatsapp-configure">
                                    <i class="bi bi-gear me-2 text-secondary"></i>
                                    <span>WhatsApp Settings</span>
                                </a>
                            </li>
                        @elseif($canViewWhatsapp)
                            <li class="nav-item mt-1">
                                <a class="nav-link ccc ddd @if(request()->routeIs('whatsapp.settings')) active @endif"
                                    href="{{ route('whatsapp.settings') }}">
                                    <i class="bi bi-gear me-2 text-secondary"></i>
                                    <span>WhatsApp Settings</span>
                                </a>
                            </li>
                        @endif
                    @endif
                    
                    <li class="nav-item mt-1">
                        <a class="nav-link ccc ddd @if(request()->routeIs('profile.show')) active @endif"
                            href="{{ route('profile.show') }}">
                            <i class="bi bi-person me-2 text-primary"></i>
                            <span>Manage Profile</span>
                        </a>
                    </li>
                    <li class="nav-item mt-1">
                        <a class="nav-link ccc ddd" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2 text-danger"></i>
                            <span>Logout</span>
                        </a>
                    </li>

                    {{-- HIDDEN MODULES START (kept for future use, only WhatsApp is shown)
                    @if(auth()->user()?->isAdmin())
                        <li class="nav-item mt-2">
                            <a class="nav-link ccc ddd @if(request()->routeIs('facebook.*')) active @endif"
                                href="{{ route('facebook.index') }}">
                                <i class="fab fa-facebook me-2" style="color: #1877F2;"></i>
                                <span>Facebook Leads</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_followups'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_followups'), request()->routeIs('followups.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_followups'), route('followups.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_followups')) aria-disabled="true"
                                tabindex="-1" @endunless>
                                <i class="fa fa-user-tie me-2 text-secondary"></i>
                                <span>Manage Follow up</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_meetings'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_meetings'), request()->routeIs('meetings.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_meetings'), route('meetings.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_meetings')) aria-disabled="true"
                                tabindex="-1" @endunless>
                                <i class="fa-solid fa-handshake me-2 text-success"></i>
                                <span>Manage Meetings</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_deals'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_deals'), request()->routeIs('deals.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_deals'), route('deals.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_deals')) aria-disabled="true" tabindex="-1"
                                @endunless>
                                <i class="fa-solid fa-medal me-2 text-warning"></i>
                                <span>Manage Deals</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_tasks'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_tasks'), request()->routeIs('tasks.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_tasks'), route('tasks.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_tasks')) aria-disabled="true" tabindex="-1"
                                @endunless>
                                <i class="fa fa-tasks me-2 text-info"></i>
                                <span>Manage Tasks</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_stages'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_stages'), request()->routeIs('masters.stages.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_stages'), route('masters.stages.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_stages')) aria-disabled="true" tabindex="-1"
                                @endunless>
                                <i class="fa-solid fa-stairs me-2 text-primary"></i>
                                <span>Manage Stages</span>
                            </a>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_pipeline'))
                        <li class="nav-item mt-2">
                            <a class="{{ $navLinkClasses((bool) auth()->user()?->hasMatrixPermission('view_pipeline'), request()->routeIs('pipeline.*')) }}"
                                href="{{ $navHref((bool) auth()->user()?->hasMatrixPermission('view_pipeline'), route('pipeline.index')) }}"
                                @unless(auth()->user()?->hasMatrixPermission('view_pipeline')) aria-disabled="true"
                                tabindex="-1" @endunless>
                                <i class="fa fa-project-diagram me-2 text-success"></i>
                                <span>Manage Pipeline</span>
                            </a>
                        </li>
                    @endif

                    @php
                        $hasProjects  = auth()->user()?->hasMatrixPermission('view_projects');
                        $hasInvoices  = auth()->user()?->hasMatrixPermission('view_invoices');
                        $hasTickets   = auth()->user()?->hasMatrixPermission('view_tickets');
                        $hasProducts  = auth()->user()?->hasMatrixPermission('view_products');
                        $hasServices  = auth()->user()?->hasMatrixPermission('view_services');
                        $workspaceActive = request()->routeIs('projects.*')
                            || request()->routeIs('invoices.*')
                            || request()->routeIs('tickets.*')
                            || request()->routeIs('products.*')
                            || request()->routeIs('services.*');
                    @endphp
                    @if ($hasProjects || $hasInvoices || $hasTickets || $hasProducts || $hasServices)
                        <li class="nav-item mt-2 nav-group" id="navGroupWorkspace">
                            <a class="nav-link ccc ddd nav-group__toggle @if($workspaceActive) active @endif"
                                href="javascript:void(0)"
                                role="button"
                                aria-expanded="{{ $workspaceActive ? 'true' : 'false' }}"
                                aria-controls="navGroupWorkspaceMenu">
                                <i class="fa-solid fa-briefcase me-2 text-primary"></i>
                                <span>Workspace</span>
                                <i class="nav-group__caret fa-solid fa-chevron-down ms-auto"></i>
                            </a>
                            <ul class="nav-group__menu nav flex-column" id="navGroupWorkspaceMenu"
                                style="{{ $workspaceActive ? '' : 'display:none;' }}">

                                @if ($hasProjects)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd ps-5 @if(request()->routeIs('projects.*')) active @endif"
                                            href="{{ route('projects.index') }}">
                                            <i class="fas fa-project-diagram me-2 text-danger opacity-75"></i>
                                            <span>Projects</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($hasInvoices)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd ps-5 @if(request()->routeIs('invoices.*')) active @endif"
                                            href="{{ route('invoices.index') }}">
                                            <i class="fas fa-file-invoice me-2 text-danger opacity-75"></i>
                                            <span>Invoices</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($hasTickets)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd ps-5 @if(request()->routeIs('tickets.*')) active @endif"
                                            href="{{ route('tickets.index') }}">
                                            <i class="fa fa-ticket me-2 text-info opacity-75"></i>
                                            <span>Tickets</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($hasProducts)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd @if(request()->routeIs('products.index', 'products.create', 'products.edit', 'products.show')) active @endif"
                                            href="{{ route('products.index') }}">
                                            <i class="fa fa-cube me-2 text-warning opacity-75"></i>
                                            <span>Products</span>
                                        </a>
                                    </li>
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd @if(request()->routeIs('products.categories.*')) active @endif"
                                            href="{{ route('products.categories.index') }}">
                                            <i class="fa fa-tags me-2 text-secondary opacity-75"></i>
                                            <span>Categories</span>
                                        </a>
                                    </li>
                                @endif

                                @if ($hasServices)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd ps-5 @if(request()->routeIs('services.*')) active @endif"
                                            href="{{ route('services.index') }}">
                                            <i class="fa fa-wrench me-2 text-secondary opacity-75"></i>
                                            <span>Services</span>
                                        </a>
                                    </li>
                                @endif

                            </ul>
                        </li>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_reports'))
                        <li class="nav-item mt-2 nav-group" id="navGroupReports">
                            <a class="nav-link ccc ddd nav-group__toggle @if(request()->routeIs('reports.*')) active @endif"
                                href="javascript:void(0)"
                                role="button"
                                aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}"
                                aria-controls="navGroupReportsMenu">
                                <i class="fa-solid fa-file-lines me-2 text-dark"></i>
                                <span>Reports</span>
                                <i class="nav-group__caret fa-solid fa-chevron-down ms-auto"></i>
                            </a>
                            <ul class="nav-group__menu nav flex-column ps-3" id="navGroupReportsMenu"
                                style="{{ request()->routeIs('reports.*') ? '' : 'display:none;' }}">
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.customers')) active @endif"
                                        href="{{ route('reports.customers') }}">
                                        <i class="fa-solid fa-users me-2 text-success opacity-75"></i>
                                        <span>Customer Reports</span>
                                    </a>
                                </li>
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.leads')) active @endif"
                                        href="{{ route('reports.leads') }}">
                                        <i class="fa-solid fa-bullhorn me-2 text-warning opacity-75"></i>
                                        <span>Lead Reports</span>
                                    </a>
                                </li>
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.deals')) active @endif"
                                        href="{{ route('reports.deals') }}">
                                        <i class="fa-solid fa-medal me-2 text-warning opacity-75"></i>
                                        <span>Deal Reports</span>
                                    </a>
                                </li>
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.projects')) active @endif"
                                        href="{{ route('reports.projects') }}">
                                        <i class="fas fa-project-diagram me-2 text-danger opacity-75"></i>
                                        <span>Project Reports</span>
                                    </a>
                                </li>
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.tasks')) active @endif"
                                        href="{{ route('reports.tasks') }}">
                                        <i class="fa fa-tasks me-2 text-info opacity-75"></i>
                                        <span>Task Reports</span>
                                    </a>
                                </li>
                                <li class="nav-item mt-1">
                                    <a class="nav-link ccc ddd @if(request()->routeIs('reports.followups')) active @endif"
                                        href="{{ route('reports.followups') }}">
                                        <i class="fa fa-user-tie me-2 text-secondary opacity-75"></i>
                                        <span>Followup Reports</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @php
                        $hasEmail = auth()->user()?->hasMatrixPermission('view_email');
                        $hasSms   = auth()->user()?->hasMatrixPermission('view_sms_marketing');
                        $marketingActive = request()->routeIs('marketing.templates.*') || request()->routeIs('marketing.sms_marketing.*');
                    @endphp
                    @if ($hasEmail || $hasSms)
                        <!-- Marketing group -->
                        <li class="nav-item mt-2 nav-group" id="navGroupMarketing">
                            <a class="nav-link ccc ddd nav-group__toggle @if($marketingActive) active @endif"
                                href="javascript:void(0)"
                                role="button"
                                aria-expanded="{{ $marketingActive ? 'true' : 'false' }}"
                                aria-controls="navGroupMarketingMenu">
                                <i class="fa fa-paper-plane me-2 text-info"></i>
                                <span>Marketing</span>
                                <i class="nav-group__caret fa-solid fa-chevron-down ms-auto"></i>
                            </a>
                            <ul class="nav-group__menu nav flex-column ps-3" id="navGroupMarketingMenu"
                                style="{{ $marketingActive ? '' : 'display:none;' }}">
                                @if ($hasEmail)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd @if(request()->routeIs('marketing.templates.*')) active @endif"
                                            href="{{ route('marketing.templates.index') }}">
                                            <i class="fa fa-envelope me-2 text-info opacity-75"></i>
                                            <span>Email Marketing</span>
                                        </a>
                                    </li>
                                @endif
                                @if ($hasSms)
                                    <li class="nav-item mt-1">
                                        <a class="nav-link ccc ddd @if(request()->routeIs('marketing.sms_marketing.*')) active @endif"
                                            href="{{ route('marketing.sms_marketing.index') }}">
                                            <i class="fa fa-message me-2 text-success opacity-75"></i>
                                            <span>SMS Marketing</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    HIDDEN MODULES END --}}

                    {{-- Settings link removed from sidebar per request --}}

                </nav>
            </aside>
            <div class="crm-sidebar-backdrop" id="crmSidebarBackdrop"></div>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-grow-1 overflow-auto">
                <!-- Topbar -->
                <header class="crm-topbar">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div class="d-flex align-items-center gap-4">
                            <button id="mobileSidebarToggle" class="crm-sidebar-toggle d-lg-none" type="button" aria-label="Open menu"
                                aria-controls="sidenav-main" aria-expanded="false">
                                <i class="bi bi-list"></i>
                            </button>
                            <button id="sidebarToggle" class="crm-sidebar-toggle d-none d-lg-block" type="button" aria-label="Toggle sidebar"
                                aria-controls="sidenav-main" aria-expanded="true">
                                <i class="bi bi-list"></i>
                            </button>
                            
                            <!-- Mobile Logo -->
                            <a href="{{ route('whatsapp.inbox') }}" class="d-lg-none d-flex align-items-center text-decoration-none">
                                @php
                                    $mobileLogo = crm_asset('images/logo1.png');
                                @endphp
                                <img src="{{ $mobileLogo }}" alt="Fablead" class="mobile-topbar-logo" style="height: 54px; max-width: 180px; object-fit: contain;border-radius: 6px;">
                            </a>
                            <!-- <div class="search-wrapper d-none d-lg-block position-relative" id="globalSearchWrapper">
                                <i class="bi bi-search text-muted"></i>
                                <input type="text" class="form-control bg-light border-0" id="globalSearchInput"
                                    placeholder="Search anything..." autocomplete="off" style="width: 400px">
                                <div class="search-results-dropdown shadow-lg rounded-4 border-0 position-absolute w-100 bg-white mt-2 p-2"
                                    id="globalSearchResults"
                                    @if($canViewWhatsapp) data-whatsapp-url="{{ route('whatsapp.inbox') }}" data-whatsapp-analytics-url="{{ route('whatsapp.analytics') }}" @endif
                                    @if(auth()->user()?->isAdmin()) data-settings-url="{{ route('settings.index') }}" @endif
                                    style="z-index: 1050; max-height: 400px; overflow-y: auto; min-width: 340px; top: 100%;">
                                </div>
                            </div> -->
                        </div>


                        @php
                            $topbarAvatar = Auth::user()?->avatar_path
                                ? route('users.image', Auth::user()) . '?v=' . optional(Auth::user()->updated_at)->timestamp
                                : (Auth::user()?->avatar_url ??
                                    'https://ui-avatars.com/api/?name=Admin&background=3b82f6&color=ffffff&size=128');
                        @endphp
                        <div class="crm-top-actions">

                            <button id="hardRefreshBtn" type="button" title="Hard refresh" aria-label="Hard refresh">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>

                            <!-- Dark Mode Toggle -->
                            <button id="darkModeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                                <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
                            </button>

                            <div class="dropdown">
                                <button class="notification-btn" type="button" id="notificationTrigger"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    title="Notifications" aria-label="Notifications">
                                    <i class="bi bi-bell"></i>
                                    <span class="notification-badge" id="notificationTriggerBadge" hidden>0</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end notifications-dropdown mt-2" id="notificationsDropdown">
                                    <div class="notification-row notification-row--empty">
                                        <span class="notification-avatar"><i class="bi bi-bell"></i></span>
                                        <div class="notification-message">No notifications yet.</div>
                                    </div>
                                    <a href="{{ route('notifications.list') }}" class="view-all-notifications text-decoration-none">View all</a>
                                </div>
                            </div>

                            <div class="vr mx-2 text-muted opacity-25"></div>

                            <div class="dropdown">
                                <button class="btn text-decoration-none crm-user-trigger" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="{{ $topbarAvatar ?? (Auth::user()?->avatar_url ?? 'https://ui-avatars.com/api/?name=Admin&background=3b82f6&color=ffffff&size=128') }}"
                                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Admin&background=3b82f6&color=ffffff&size=128';"
                                        alt="{{ Auth::user()?->name ?? 'Administrator' }}" class="crm-user-avatar">
                                    <div class="crm-user-meta">
                                        <span class="crm-user-name">{{ $authUser?->name ?? 'Administrator' }}</span>
                                        <span class="crm-user-role">{{ $userRoleLabel }}</span>
                                    </div>
                                    <i class="bi bi-chevron-down crm-user-caret"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end mt-2 old-crm-user-menu">
                                    <li><a class="dropdown-item {{ request()->routeIs('profile.show') ? 'active' : '' }}"
                                            href="{{ route('profile.show') }}"><i
                                                class="bi bi-person-fill"></i><span>Profile</span></a></li>

                                    @if(auth()->user()?->isAdmin())
                                        <li><a class="dropdown-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                                                href="{{ route('settings.index') }}"><i
                                                    class="bi bi-gear-fill"></i><span>Settings</span></a></li>
                                    @endif
                                    <li>
                                        <a class="dropdown-item" href="{{ route('logout') }}"
                                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="{{ request()->routeIs('whatsapp.inbox', 'whatsapp.conversation') ? 'wa-mobile-main mb-0' : 'p-4 p-lg-4 mb-5' }}">
                    @unless(request()->routeIs('dashboard'))
                        <div class="mb-3 d-none d-lg-flex justify-content-between align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item text-muted">Home</li>
                                    <li class="breadcrumb-item active" aria-current="page">@yield('page_title', 'Dashboard')
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    @else
                        @yield('page_actions')
                    @endunless
                    @if(session('success'))
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: "{{ session('success') }}",
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true,
                                    customClass: {
                                        popup: 'rounded-4 shadow'
                                    }
                                });
                            });
                        </script>
                    @endif

                    @if(session('error'))
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: "{{ session('error') }}",
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true,
                                    customClass: {
                                        popup: 'rounded-4 shadow'
                                    }
                                });
                            });
                        </script>
                    @endif

                    <div id="crmNotifPermissionBanner" class="crm-notif-permission-banner" role="status" style="display:none !important;" hidden>
                        <div>
                            <strong>Enable desktop notifications</strong>
                            <div class="small opacity-75">Get alerts for new WhatsApp messages even when this tab is minimized.</div>
                        </div>
                        <div class="crm-notif-permission-banner__actions">
                            <button type="button" class="btn btn-success" id="crmEnableBrowserNotifBtn">Enable</button>
                            <button type="button" class="btn btn-outline-secondary" id="crmDismissBrowserNotifBtn">Not now</button>
                        </div>
                    </div>

                    @yield('content')
                </main>

                <!-- Mobile Bottom Navigation removed as per request -->
                {{-- 
                <div class="crm-mobile-nav d-lg-none">
                    <a href="{{ route('dashboard') }}"
                        class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-house"></i>
                        <span>Home</span>
                    </a>

                    @if (auth()->user()?->hasMatrixPermission('view_leads'))
                        <a href="{{ route('leads.index') }}"
                            class="mobile-nav-item {{ request()->routeIs('leads.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-bullhorn"></i>
                            <span>Leads</span>
                        </a>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_customers'))
                        <a href="{{ route('masters.customers.index') }}"
                            class="mobile-nav-item {{ request()->routeIs('masters.customers.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-users"></i>
                            <span>Customers</span>
                        </a>
                    @endif

                    @if (auth()->user()?->hasMatrixPermission('view_deals'))
                        <a href="{{ route('deals.index') }}"
                            class="mobile-nav-item {{ request()->routeIs('deals.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-file-invoice"></i>
                            <span>Deals</span>
                        </a>
                    @endif

                    <a href="{{ route('profile.show') }}"
                        class="mobile-nav-item {{ request()->routeIs('profile.show') ? 'active' : '' }}">
                        <i class="fa-solid fa-user"></i>
                        <span>Profile</span>
                    </a>
                </div>
                --}}
            </div>
        @endauth

        @guest
            <main class="w-100">
                @yield('content')
            </main>
        @endguest
    </div>

    <div class="modal fade status-comment-modal" id="statusCommentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0">
                    <h5 class="modal-title mb-0">Add Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="statusCommentInput" class="form-control" rows="4" maxlength="2000"
                        placeholder="Enter your message"></textarea>
                    <div class="invalid-feedback d-block d-none" id="statusCommentError">Comment is required.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark-blue" id="statusCommentSaveBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- @auth
        <div class="chatbot-float-btn chatbot-toggle-btn" id="chatbotToggleBtn" role="button" aria-label="Open chatbot">
            <i class="fa-solid fa-headset"></i>
        </div>

        {{-- @include('crm.chatbot.chatbot-modal') --}}
        
        <div class="chatbot-card" id="chatbotCard" role="dialog" aria-labelledby="chatbotCardLabel" aria-hidden="true">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="chatbot-header-icon">
                            <i class="fa-solid fa-headset"></i>
                        </span>
                        <div>
                            <h5 class="mb-0 text-white fw-semibold" id="chatbotCardLabel">Bot</h5>
                            <p class="text-light small mb-0">Always here to help</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" id="chatbotCloseBtn"
                        aria-label="Close"></button>
                </div>


                <div class="card-body p-0">
                    <div id="chatbotMessages" class="chatbot-messages">
                        <div class="chatbot-empty-state pt-0">
                            👋 Hi there!<br>
                            Ask me anything about <strong>leads, customers, tickets, reports,</strong> or CRM settings.
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div id="chatbotInputWrapper" class="d-none">
                        <label for="chatbotInput" class="form-label mb-1 ps-3">What's your question?</label>
                        <div class="input-group">
                            <input id="chatbotInput" type="text" class="form-control"
                                placeholder="Type your message here..." aria-label="Chat message">
                            <button type="button" class="btn btn-dark-blue" id="chatbotSendBtn">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endauth
 -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        if (window.jQuery) {
            $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                const token = window.crmAuthToken();

                if (token) {
                    if (!options.headers) {
                        options.headers = {};
                    }
                    if (!options.headers.Authorization) {
                        options.headers.Authorization = 'Bearer ' + token;
                    }
                }
            });
        }

        if (window.axios?.defaults?.headers?.common) {
            const token = window.crmAuthToken();

            if (token) {
                window.axios.defaults.headers.common.Authorization = 'Bearer ' + token;
            }
        }
    </script>

    @stack('scripts')
    <script
        src="{{ crm_asset('js/crm-layout.js', filemtime(public_path('js/crm-layout.js'))) }}"></script>
    <script
        src="{{ crm_asset('js/status-comment-box.js', filemtime(public_path('js/status-comment-box.js'))) }}"></script>
    <!-- Bootstrap JS -->
    <!-- SweetAlert2 -->
    <script src="{{ crm_asset('js/main.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    @auth
        <script
            src="{{ crm_asset('js/chatbot.js', filemtime(public_path('js/chatbot.js'))) }}"></script>
        <script
            src="{{ crm_asset('js/global-search.js', filemtime(public_path('js/global-search.js'))) }}"></script>
    @endauth
    @auth
        <div class="modal fade dashboard-reminder-modal" id="dashboardReminderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0">
                        <div>
                            <p class="dashboard-reminder-kicker mb-1">Reminder Window</p>
                            <h5 class="modal-title mb-0" id="dashboardReminderTitle">Upcoming Schedule</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <div class="dashboard-reminder-panels">
                            <section class="dashboard-reminder-section">
                                <div class="dashboard-reminder-section__head">
                                    <h6 class="mb-0">Today's Meetings</h6>
                                    <span class="dashboard-reminder-pill" id="dashboardMeetingReminderCount">0</span>
                                </div>
                                <div id="dashboardMeetingReminderList" class="dashboard-reminder-list"></div>
                            </section>
                            <section class="dashboard-reminder-section">
                                <div class="dashboard-reminder-section__head">
                                    <h6 class="mb-0">Today's Follow Ups</h6>
                                    <span class="dashboard-reminder-pill dashboard-reminder-pill--rose" id="dashboardFollowUpReminderCount">0</span>
                                </div>
                                <div id="dashboardFollowUpReminderList" class="dashboard-reminder-list"></div>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-dark-blue" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let lastNotificationId = 0;
                let notificationsReady = false;
                const notificationPollUrl = @json(route('notifications.poll'));
                const inboxUrl = @json(route('whatsapp.inbox'));

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            const notifBanner = document.getElementById('crmNotifPermissionBanner');
            const notifDismissKey = 'crm_notif_banner_dismissed_at';
            const notifIconUrl = @json(asset('images/logo1.png'));

            function browserNotificationsSupported() {
                return ('Notification' in window) && window.isSecureContext;
            }

            function updateNotifPermissionBanner() {
                if (!notifBanner) return;

                if (!browserNotificationsSupported()) {
                    notifBanner.classList.remove('is-visible');
                    return;
                }

                if (Notification.permission === 'granted') {
                    notifBanner.classList.remove('is-visible');
                    return;
                }

                if (Notification.permission === 'denied') {
                    notifBanner.querySelector('strong').textContent = 'Desktop notifications are blocked';
                    notifBanner.querySelector('.small').textContent = 'Allow notifications for this site in your browser settings, then refresh.';
                    const enableBtn = document.getElementById('crmEnableBrowserNotifBtn');
                    if (enableBtn) enableBtn.style.display = 'none';
                    notifBanner.classList.add('is-visible');
                    return;
                }

                const dismissedAt = Number(localStorage.getItem(notifDismissKey) || 0);
                // Re-show prompt after 12 hours if still not granted.
                if (dismissedAt && (Date.now() - dismissedAt) < (12 * 60 * 60 * 1000)) {
                    notifBanner.classList.remove('is-visible');
                    return;
                }

                notifBanner.classList.add('is-visible');
            }

            function ensureBrowserNotificationPermission() {
                if (!browserNotificationsSupported()) {
                    return Promise.resolve('unsupported');
                }

                if (Notification.permission === 'granted' || Notification.permission === 'denied') {
                    updateNotifPermissionBanner();
                    return Promise.resolve(Notification.permission);
                }

                return Notification.requestPermission().then(function (permission) {
                    updateNotifPermissionBanner();
                    return permission;
                }).catch(function () {
                    updateNotifPermissionBanner();
                    return Notification.permission;
                });
            }

            function isViewingNotificationTarget(link) {
                if (!link) {
                    return false;
                }
                try {
                    const current = window.location.href.split('?')[0].replace(/\/$/, '');
                    const target = String(link).split('?')[0].replace(/\/$/, '');
                    return current === target;
                } catch (e) {
                    return false;
                }
            }

            function getLiveToastHost() {
                let host = document.getElementById('crmLiveToastHost');
                if (!host) {
                    host = document.createElement('div');
                    host.id = 'crmLiveToastHost';
                    host.className = 'crm-live-toast-host';
                    document.body.appendChild(host);
                }
                return host;
            }

            function showInAppToast(notification) {
                if (document.hidden) {
                    return;
                }

                const link = notification?.link || inboxUrl;
                const text = String(notification?.notification_text || 'New WhatsApp message');
                const isWhatsApp = /whatsapp/i.test(text);
                const title = isWhatsApp ? 'Shrishti Trip WhatsApp' : 'New Notification';
                const body = text.replace(/^New WhatsApp message from\s+/i, '').slice(0, 140);

                const toast = document.createElement('div');
                toast.className = 'crm-live-toast';
                toast.innerHTML = `
                    <span class="crm-live-toast__icon"><i class="bi bi-whatsapp"></i></span>
                    <div class="min-w-0">
                        <div class="crm-live-toast__title">${escapeHtml(title)}</div>
                        <div class="crm-live-toast__body">${escapeHtml(body)}</div>
                    </div>
                `;
                toast.addEventListener('click', function () {
                    if (link) window.location.href = link;
                });
                getLiveToastHost().appendChild(toast);
                setTimeout(function () {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity .2s ease';
                    setTimeout(function () { toast.remove(); }, 220);
                }, 7000);
            }

            function showOsNotification(title, body, link, tag) {
                if (!browserNotificationsSupported() || Notification.permission !== 'granted') {
                    return null;
                }

                try {
                    const browserNotif = new Notification(title, {
                        body: body,
                        icon: notifIconUrl,
                        badge: notifIconUrl,
                        tag: tag || ('crm-notif-' + Date.now()),
                        renotify: true,
                        requireInteraction: !!document.hidden,
                    });

                    browserNotif.onclick = function () {
                        window.focus();
                        if (link) {
                            window.location.href = link;
                        }
                        browserNotif.close();
                    };

                    // Keep OS alerts longer when the tab is minimized.
                    setTimeout(function () {
                        try { browserNotif.close(); } catch (e) {}
                    }, document.hidden ? 20000 : 8000);

                    return browserNotif;
                } catch (error) {
                    console.error('Browser notification failed:', error);
                    return null;
                }
            }

            function showBrowserNotification(notification) {
                const link = notification?.link || inboxUrl;

                if (isViewingNotificationTarget(link)) {
                    return;
                }

                const text = String(notification?.notification_text || 'New WhatsApp message');
                const isWhatsApp = /whatsapp/i.test(text);
                const title = isWhatsApp ? 'Shrishti Trip WhatsApp' : 'New Notification';
                const body = text.replace(/^New WhatsApp message from\s+/i, '').slice(0, 140);

                // In-app toast only useful while the tab is visible.
                if (!document.hidden) {
                    showInAppToast(notification);
                }

                // Desktop/OS notification — works when minimized if permission is granted.
                showOsNotification(
                    title,
                    body,
                    link,
                    'crm-notif-' + (notification?.id || Date.now())
                );
            }

            // Expose for conversation page / other scripts.
            window.crmShowOsNotification = showOsNotification;
            window.crmEnsureBrowserNotificationPermission = ensureBrowserNotificationPermission;

            function renderNotificationRow(notification) {
                const id = notification?.id ?? '';
                const message = escapeHtml(notification?.notification_text || 'You have a new notification.');
                const time = escapeHtml((notification?.created_at || '').replace('T', ' ').slice(0, 19));
                const link = escapeHtml(notification?.link || '');

                return `
                    <div class="notification-row" data-notification-id="${id}" ${link ? `data-link="${link}" style="cursor:pointer;"` : ''}>
                        <span class="notification-avatar">
                            <i class="bi bi-bell"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="notification-message">${message}</div>
                            <div class="d-flex align-items-center gap-1">
                                <i class="bi bi-clock"></i>
                                <div class="notification-time mt-0">${time}</div>
                            </div>
                        </div>
                        <div class="ms-2">
                            <i class="fa fa-times float-right mark-as-read" data-id="${id}"
                                style="cursor: pointer; color: #94a3b8;"></i>
                        </div>
                    </div>
                `;
            }

            function updateNotificationBadge(count) {
                const badge = document.getElementById('notificationTriggerBadge');
                if (!badge) {
                    return;
                }

                const safeCount = Number(count) || 0;
                badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
                if (safeCount > 0) {
                    badge.hidden = false;
                    badge.removeAttribute('hidden');
                } else {
                    badge.hidden = true;
                    badge.setAttribute('hidden', 'hidden');
                }
            }

            function prependNotifications(items) {
                if (!items || !items.length) {
                    return;
                }

                const dropdown = document.querySelector('.notifications-dropdown');
                const viewAllLink = dropdown?.querySelector('.view-all-notifications');
                if (!dropdown || !viewAllLink) {
                    return;
                }

                const emptyState = dropdown.querySelector('.notification-row--empty');
                if (emptyState) {
                    emptyState.remove();
                }

                items
                    .slice()
                    .reverse()
                    .forEach(function (notification) {
                        if (dropdown.querySelector(`[data-notification-id="${notification.id}"]`)) {
                            return;
                        }

                        viewAllLink.insertAdjacentHTML('beforebegin', renderNotificationRow(notification));
                    });

                // Keep only the 3 most recent rows in the dropdown
                const rows = Array.from(dropdown.querySelectorAll('.notification-row:not(.notification-row--empty)'));
                if (rows.length > 3) {
                    rows.slice(3).forEach(function (row) { row.remove(); });
                }
            }

            function pollNotifications() {
                fetch(`${notificationPollUrl}?last_id=${lastNotificationId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(response => response.ok ? response.json() : null)
                    .then(payload => {
                        if (!payload?.status || !payload.data) {
                            return;
                        }

                        updateNotificationBadge(payload.data.unreadCount ?? 0);

                        if (Array.isArray(payload.data.notifications) && payload.data.notifications.length > 0) {
                            // Always refresh the bell dropdown list.
                            prependNotifications(payload.data.notifications);

                            // Only toast/browser-notify for messages that arrived after page load.
                            if (notificationsReady) {
                                payload.data.notifications.forEach(showBrowserNotification);
                            }
                            lastNotificationId = payload.data.last_id || lastNotificationId;
                        }

                        notificationsReady = true;
                    })
                    .catch(error => {
                        console.error('Notification poll failed:', error);
                    });
            }

            // Open conversation when clicking a notification row
            document.addEventListener('click', function (event) {
                const row = event.target.closest('.notification-row[data-link]');
                if (!row || event.target.classList.contains('mark-as-read')) {
                    return;
                }

                const link = row.getAttribute('data-link');
                if (link) {
                    window.location.href = link;
                }
            });

            // Ask for OS notification permission when user opens the bell (user gesture).
            const notificationTrigger = document.getElementById('notificationTrigger');
            if (notificationTrigger) {
                notificationTrigger.addEventListener('click', function () {
                    ensureBrowserNotificationPermission();
                });
            }

            const enableNotifBtn = document.getElementById('crmEnableBrowserNotifBtn');
            if (enableNotifBtn) {
                enableNotifBtn.addEventListener('click', function () {
                    ensureBrowserNotificationPermission().then(function (permission) {
                        if (permission === 'granted') {
                            showOsNotification(
                                'Shrishti Trip WhatsApp',
                                'Desktop notifications are enabled. You will get alerts when the tab is minimized.',
                                inboxUrl,
                                'crm-notif-test'
                            );
                        }
                    });
                });
            }

            const dismissNotifBtn = document.getElementById('crmDismissBrowserNotifBtn');
            if (dismissNotifBtn) {
                dismissNotifBtn.addEventListener('click', function () {
                    localStorage.setItem(notifDismissKey, String(Date.now()));
                    if (notifBanner) notifBanner.classList.remove('is-visible');
                });
            }

            updateNotifPermissionBanner();

            // Mark as Read Handler
            document.addEventListener('click', function (event) {
                if (event.target.classList.contains('mark-as-read')) {
                    const button = event.target;
                    const id = button.getAttribute('data-id');

                    if (!id) return;

                    fetch(`/notifications/${id}/read`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Find the notification row and remove it or hide it
                                const row = button.closest('.notification-row');
                                if (row) {
                                    row.classList.add('fade-out');
                                    setTimeout(() => {
                                        row.remove();

                                        // Update count
                                        const badge = document.getElementById('notificationTriggerBadge');
                                        if (badge) {
                                            let count = parseInt(badge.textContent.replace('99+', '100'), 10);
                                            count = Math.max(0, count - 1);
                                            updateNotificationBadge(count);
                                        }

                                        // If no notifications left in dropdown, show "No notifications yet"
                                        const dropdown = document.querySelector('.notifications-dropdown');
                                        if (dropdown && dropdown.querySelectorAll('.notification-row:not(.notification-row--empty)').length === 0) {
                                            const emptyState = document.createElement('div');
                                            emptyState.className = 'notification-row notification-row--empty';
                                            emptyState.innerHTML = '<span class="notification-avatar"><i class="bi bi-bell"></i></span><div class="notification-message">No notifications yet.</div>';
                                            dropdown.insertBefore(emptyState, dropdown.querySelector('.view-all-notifications'));
                                        }
                                    }, 300);
                                }
                            } else {
                                console.error('Error marking notification as read:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                }
            });

            // Live notification polling DISABLED — was flooding Hostinger max processes.
            // Re-enable later with a much slower interval / websockets if needed.
            // ensureBrowserNotificationPermission();
            // pollNotifications();
            if (notifBanner) {
                notifBanner.classList.remove('is-visible');
                notifBanner.style.display = 'none';
            }

            const logoutForm = document.getElementById('logout-form');

            if (!logoutForm) {
                return;
            }

                logoutForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                const tokenKey = window.CRM_AUTH_TOKEN_KEY || 'crm_auth_token';
                const token = window.crmAuthToken();
                const finalize = function () {
                    localStorage.removeItem(tokenKey);
                    localStorage.removeItem('crm_user');
                    localStorage.removeItem('user');
                    // Also clear the cookie
                    document.cookie = 'crm_auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    HTMLFormElement.prototype.submit.call(logoutForm);
                };

                if (!token) {
                    finalize();
                    return;
                }

                fetch('{{ url('/api/v1/logout') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }).then(function (response) {
                    console.log('Logout response:', response.status);
                    return response.json();
                }).then(function (data) {
                    console.log('Logout data:', data);
                    }).catch(function (error) {
                        console.error('Logout error:', error);
                    }).finally(finalize);
                });
            });
        </script>
    @endauth
    @auth
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const reminderTrigger = document.getElementById('dashboardReminderTrigger');
                const reminderBadge = document.getElementById('dashboardReminderTriggerBadge');
                const reminderModalElement = document.getElementById('dashboardReminderModal');
                const reminderModal = reminderModalElement && window.bootstrap
                    ? window.bootstrap.Modal.getOrCreateInstance(reminderModalElement)
                    : null;

                if (!reminderTrigger || !reminderBadge) {
                    return;
                }

                const applyReminderCount = function (count) {
                    reminderBadge.textContent = count > 99 ? '99+' : String(Math.max(0, count));
                };

                const escapeHtml = function (value) {
                    if (value === null || value === undefined) {
                        return '';
                    }

                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };

                const setText = function (id, value) {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                    }
                };

                const formatReminderCountdown = function (minutes) {
                    const totalMinutes = Number(minutes);
                    if (!Number.isFinite(totalMinutes)) {
                        return '-';
                    }

                    if (totalMinutes < 0) {
                        return 'Started';
                    }

                    if (totalMinutes === 0) {
                        return 'Now';
                    }

                    if (totalMinutes < 60) {
                        return totalMinutes === 1 ? '1 min left' : totalMinutes + ' mins left';
                    }

                    const hours = Math.floor(totalMinutes / 60);
                    const remainingMinutes = totalMinutes % 60;

                    if (remainingMinutes === 0) {
                        return hours === 1 ? '1 hr left' : hours + ' hrs left';
                    }

                    return (hours === 1 ? '1 hr' : hours + ' hrs') + ' ' + remainingMinutes + ' mins left';
                };

                const renderReminderList = function (targetId, items, emptyMessage) {
                    const container = document.getElementById(targetId);
                    if (!container) {
                        return;
                    }

                    if (!Array.isArray(items) || items.length === 0) {
                        container.innerHTML = '<div class="dashboard-reminder-empty">' + escapeHtml(emptyMessage) + '</div>';
                        return;
                    }

                    container.innerHTML = items.map(function (item) {
                        const statusClass = item.type === 'meeting'
                            ? 'dashboard-reminder-status--meeting'
                            : 'dashboard-reminder-status--followup';
                        const metaOne = item.type === 'meeting'
                            ? (item.meeting_type || 'Meeting Type')
                            : ((item.priority || 'normal').toString().replace(/^./, function (c) { return c.toUpperCase(); }) + ' Priority');
                        const metaTwo = item.location || item.assigned_to || 'Assigned';
                        const details = item.details || '';

                        return '<article class="dashboard-reminder-card dashboard-reminder-card--' + escapeHtml(item.type || 'item') + '">'
                            + '<div class="dashboard-reminder-card__top">'
                            + '<div>'
                            + '<h6 class="dashboard-reminder-card__title mb-1">' + escapeHtml(item.title || '-') + '</h6>'
                            + '<p class="dashboard-reminder-card__subtitle mb-0">' + escapeHtml(item.related_name || '-') + '</p>'
                            + '</div>'
                            + '<span class="dashboard-reminder-status ' + statusClass + '">' + escapeHtml(formatReminderCountdown(item.starts_in_minutes)) + '</span>'
                            + '</div>'
                            + '<div class="dashboard-reminder-card__time">' + escapeHtml(item.scheduled_for_text || '-') + '</div>'
                            + '<div class="dashboard-reminder-card__meta">'
                            + '<span>' + escapeHtml(metaOne) + '</span>'
                            + '<span>' + escapeHtml(metaTwo) + '</span>'
                            + '<span>' + escapeHtml(item.assigned_to || 'Unassigned') + '</span>'
                            + '</div>'
                            + (details ? '<p class="dashboard-reminder-card__details mb-0">' + escapeHtml(details) + '</p>' : '')
                            + '<div class="dashboard-reminder-card__actions">'
                            + '<a href="' + escapeHtml(item.view_url || '#') + '" class="btn btn-sm btn-outline-dark-blue">View</a>'
                            + '</div>'
                            + '</article>';
                    }).join('');
                };

                const fetchReminderPayload = function () {
                    const headers = typeof window.crmApplyAuthHeaders === 'function'
                        ? window.crmApplyAuthHeaders({
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        })
                        : {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        };

                    return fetch('{{ route('api.dashboard.upcoming_reminders') }}', {
                        method: 'GET',
                        headers: headers,
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Failed to load reminders');
                            }

                            return response.json();
                        });
                };

                const syncReminderUi = function (openModal) {
                    fetchReminderPayload()
                        .then(function (payload) {
                            const data = payload && payload.data ? payload.data : {};
                            const meetings = Array.isArray(data.meetings) ? data.meetings : [];
                            const followUps = Array.isArray(data.follow_ups) ? data.follow_ups : [];
                            const total = meetings.length + followUps.length;

                            applyReminderCount(total);
                            setText('dashboardMeetingReminderCount', String(meetings.length));
                            setText('dashboardFollowUpReminderCount', String(followUps.length));
                            setText('dashboardReminderTitle', "Today's Schedule - " + (data.date_label || ''));

                            renderReminderList(
                                'dashboardMeetingReminderList',
                                meetings,
                                'No meetings scheduled for today.'
                            );
                            renderReminderList(
                                'dashboardFollowUpReminderList',
                                followUps,
                                'No follow ups scheduled for today.'
                            );

                            if (openModal && reminderModal) {
                                reminderModal.show();
                            }
                        })
                        .catch(function () {
                            applyReminderCount(0);
                            renderReminderList(
                                'dashboardMeetingReminderList',
                                [],
                                'No meetings scheduled for today.'
                            );
                            renderReminderList(
                                'dashboardFollowUpReminderList',
                                [],
                                'No follow ups scheduled for today.'
                            );
                            if (openModal && reminderModal) {
                                reminderModal.show();
                            }
                        });
                };

                reminderTrigger.dataset.bound = 'true';
                reminderTrigger.addEventListener('click', function () {
                    syncReminderUi(true);
                });

                syncReminderUi(false);
                window.setInterval(function () {
                    syncReminderUi(false);
                }, 60000);
            });
        </script>
    @endauth

</body>

</html>
