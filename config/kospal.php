<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;

return [

    'locales' => [
        'en' => 'English',
        'fr' => 'Français',
        'rn' => 'Kirundi',
    ],

    'currencies' => [
        'KES' => 'Kenyan Shilling',
        'BIF' => 'Burundian Franc',
        'USD' => 'US Dollar',
    ],

    'countries' => [
        'KE' => 'Kenya',
        'BI' => 'Burundi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Business membership roles (never includes platform_super_admin)
    |--------------------------------------------------------------------------
    */

    'business_roles' => array_column(BusinessRole::cases(), 'value'),

    'roles' => [
        'platform_super_admin',
        ...array_column(BusinessRole::cases(), 'value'),
    ],

    'subscription_statuses' => array_column(SubscriptionStatus::cases(), 'value'),

    /*
    |--------------------------------------------------------------------------
    | Plans and hard limits (enforced server-side)
    |--------------------------------------------------------------------------
    */

    'plans' => [
        Plan::Starter->value => [
            'name' => 'Starter',
            'description' => 'One store with core catalog, sales, and inventory.',
            'max_branches' => 1,
            'max_staff' => 5,
            'features' => [
                'core',
            ],
        ],
        Plan::Pro->value => [
            'name' => 'Pro',
            'description' => 'Multi-branch retail with transfers, advanced reports, and CSV export.',
            'max_branches' => 3,
            'max_staff' => 25,
            'features' => [
                'core',
                'advanced_reports',
                'csv_export',
                'csv_import',
                'excel_import',
                'excel_export',
                'pdf_reports',
                'stock_transfers',
                'purchase_orders',
                'stock_counts',
                'customer_credit',
            ],
        ],
        Plan::Enterprise->value => [
            'name' => 'Enterprise',
            'description' => 'Consolidated reporting, audit logs, and the highest branch capacity.',
            'max_branches' => 10,
            'max_staff' => null,
            'features' => [
                'core',
                'advanced_reports',
                'csv_export',
                'csv_import',
                'excel_import',
                'excel_export',
                'pdf_reports',
                'stock_transfers',
                'purchase_orders',
                'stock_counts',
                'audit_logs',
                'consolidated_reports',
                'customer_credit',
            ],
        ],
    ],

    'default_plan' => Plan::Starter->value,

    'default_subscription_days' => (int) env('KOSPAL_DEFAULT_SUBSCRIPTION_DAYS', 30),

    'default_trial_days' => (int) env('KOSPAL_LICENSE_TRIAL_DAYS', 30),

    'invitation_expires_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | Attachments (local-first; disk is swappable via filesystems.php)
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'attachments'),
        'max_kilobytes' => 5120,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

];
