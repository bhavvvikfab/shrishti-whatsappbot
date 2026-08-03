@extends('layouts.app')

@section('page_title', 'All Notifications')

@section('content')
    <div class="container-fluid p-0">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-bottom-0 py-3 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold mb-0">Notifications</h4>
                        <p class="text-muted small mb-0">Your recent alerts and system messages.</p>
                    </div>
                    <div class="notif-actions d-none ms-auto" id="notifActionBtns">
                        <button id="markAllReadBtn" class="btn-notif-action btn-notif-read" type="button">
                            <span class="btn-notif-action__icon">
                                <i class="fa-solid fa-check-double"></i>
                            </span>
                            <span class="btn-notif-action__label">Mark All Read</span>
                        </button>
                        <button id="deleteAllNotificationsBtn" class="btn-notif-action btn-notif-delete" type="button">
                            <span class="btn-notif-action__icon">
                                <i class="fa-solid fa-trash-can"></i>
                            </span>
                            <span class="btn-notif-action__label">Delete All</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="notification-list" id="notificationList">
                    {{-- Notifications will be loaded here via AJAX --}}
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white py-4 px-4" id="notificationsPagination"></div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .notification-list {
            display: flex;
            flex-direction: column;
        }

        .notification-list .notification-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid var(--crm-border);
            transition: background .15s ease;
        }

        .notification-list .notification-row:hover {
            background: #F8FAFC;
        }

        .notification-list .notification-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(59, 91, 219, .1);
            color: var(--crm-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .notification-list .notification-message {
            font-size: .95rem;
            line-height: 1.4;
            color: var(--crm-text-body);
        }

        .notification-list .notification-time {
            font-size: .8rem;
            color: var(--crm-text-muted);
        }

        /* ── Action buttons wrapper ── */
        .notif-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
            padding-left: 16px;
        }

        /* ── Shared pill button base ── */
        .btn-notif-action {
            display: inline-flex;
            align-items: center;
            gap: 0;
            padding: 4px 14px 4px 4px;
            min-height: 36px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .btn-notif-action__icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 35%, #ffffff, #e4e4e4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,.15), inset 0 1px 1px rgba(255,255,255,.9);
        }

        .btn-notif-action__icon i {
            font-size: .75rem;
            transition: transform .2s ease;
        }

        .btn-notif-action__label {
            font-size: .78rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: .02em;
            padding-left: 9px;
            text-shadow: 0 1px 2px rgba(0,0,0,.15);
        }

        .btn-notif-action:hover {
            transform: translateY(-1px);
        }

        .btn-notif-action:hover .btn-notif-action__icon i {
            transform: scale(1.15);
        }

        .btn-notif-action:active {
            transform: translateY(0);
        }

        .btn-notif-action:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Mark All Read — dark navy ── */
        .btn-notif-read {
            background: linear-gradient(145deg, #2d3f55, #1E293B);
            box-shadow: 0 3px 10px rgba(30, 41, 59, .40), inset 0 1px 0 rgba(255,255,255,.10);
        }

        .btn-notif-read .btn-notif-action__icon i {
            color: #1E293B;
        }

        .btn-notif-read:hover {
            box-shadow: 0 5px 16px rgba(30, 41, 59, .55), inset 0 1px 0 rgba(255,255,255,.13);
            filter: brightness(1.12);
        }

        /* ── Delete All — red ── */
        .btn-notif-delete {
            background: linear-gradient(145deg, #e84040, #c0392b);
            box-shadow: 0 3px 10px rgba(192, 57, 43, .38), inset 0 1px 0 rgba(255,255,255,.12);
        }

        .btn-notif-delete .btn-notif-action__icon i {
            color: #c0392b;
        }

        .btn-notif-delete:hover {
            box-shadow: 0 5px 16px rgba(192, 57, 43, .50), inset 0 1px 0 rgba(255,255,255,.15);
            filter: brightness(1.06);
        }

        .btn-notif-delete:hover .btn-notif-action__icon i {
            transform: scale(1.15) rotate(-8deg);
        }

        /* Dark mode */
        [data-theme="dark"] .btn-notif-read {
            background: linear-gradient(145deg, #3a4f6a, #2d3f55);
        }

        [data-theme="dark"] .btn-notif-delete {
            background: linear-gradient(145deg, #e84040, #b03020);
        }

        /* ── Empty state ── */
        .notif-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 56px 24px 64px;
            user-select: none;
        }

        .notif-empty-state__scene {
            position: relative;
            width: 180px;
            height: 180px;
            margin-bottom: 28px;
        }

        /* soft blob behind the bell */
        .notif-empty-state__blob {
            position: absolute;
            inset: 10px;
            border-radius: 50% 42% 55% 45% / 45% 55% 42% 50%;
            background: #f0f2f5;
        }

        [data-theme="dark"] .notif-empty-state__blob {
            background: rgba(255,255,255,.05);
        }

        /* bell SVG wrapper */
        .notif-empty-state__bell {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notif-empty-state__bell svg {
            width: 110px;
            height: 110px;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,.08));
            animation: notif-bell-sway 3.5s ease-in-out infinite;
            transform-origin: top center;
        }

        @keyframes notif-bell-sway {
            0%, 100% { transform: rotate(0deg); }
            20%       { transform: rotate(6deg); }
            40%       { transform: rotate(-5deg); }
            60%       { transform: rotate(4deg); }
            80%       { transform: rotate(-3deg); }
        }

        /* floating + dots */
        .notif-empty-state__dot {
            position: absolute;
            border-radius: 50%;
            background: #cbd5e1;
            opacity: .7;
        }

        [data-theme="dark"] .notif-empty-state__dot {
            background: rgba(255,255,255,.18);
        }

        .notif-empty-state__plus {
            position: absolute;
            font-size: 1.1rem;
            font-weight: 300;
            color: #cbd5e1;
            line-height: 1;
        }

        [data-theme="dark"] .notif-empty-state__plus {
            color: rgba(255,255,255,.18);
        }

        .notif-empty-state__title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 6px;
        }

        [data-theme="dark"] .notif-empty-state__title {
            color: #e2e8f0;
        }

        .notif-empty-state__sub {
            font-size: .88rem;
            color: #94a3b8;
        }

        @media (max-width: 575.98px) {
            .notif-actions {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
                padding-left: 0;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.crmNotificationsListUrl = "{{ route('notifications.list') }}";
        window.crmDeleteAllNotificationsUrl = "{{ route('notifications.delete-all') }}";
        window.crmMarkAllReadUrl = "{{ route('notifications.mark-all-read') }}";
        window.crmCsrfToken = "{{ csrf_token() }}";
    </script>
    <script
        src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/notification.js') }}?v={{ filemtime(public_path('js/notification.js')) }}"></script>
@endpush
