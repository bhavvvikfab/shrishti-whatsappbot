<?php

return [
    'actions' => [
        'view' => 'View',
        'create' => 'Insert',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    'modules' => [
        'followups' => ['label' => 'Followup', 'icon' => 'bi-person-lines-fill'],
        'leads' => ['label' => 'Leads', 'icon' => 'bi-megaphone-fill'],
        'customers' => ['label' => 'Customers', 'icon' => 'bi-people-fill'],
        'stages' => ['label' => 'Stages', 'icon' => 'bi-bar-chart-steps'],
        'pipeline' => ['label' => 'Pipeline', 'icon' => 'bi-funnel-fill'],
        'products' => ['label' => 'Products', 'icon' => 'bi-box-seam'],
        'services' => ['label' => 'Services', 'icon' => 'bi-gear-fill'],
        'invoices' => ['label' => 'Invoice', 'icon' => 'bi-receipt'],
        'tickets' => ['label' => 'Tickets', 'icon' => 'bi-ticket-perforated'],
        'tasks' => ['label' => 'Tasks', 'icon' => 'bi-list-task'],
        'projects' => ['label' => 'Projects', 'icon' => 'bi-diagram-3-fill'],
        'deals' => ['label' => 'Deals', 'icon' => 'bi-award-fill'],
        'meetings' => ['label' => 'Meetings', 'icon' => 'bi-camera-video-fill'],
        'whatsapp' => [
            'label' => 'WhatsApp Inbox',
            'icon' => 'bi-whatsapp',
            'actions' => ['view'],
        ],
        'email' => ['label' => 'Email', 'icon' => 'bi-envelope-fill'],
        'reports' => [
            'label' => 'Reports',
            'icon' => 'bi-file-earmark-text',
            'actions' => ['view'],
        ],
        'sms_marketing' => [
            'label' => 'SMS Marketing',
            'icon' => 'bi-chat-dots-fill',
            'actions' => ['view'],
        ],
    ],
];
