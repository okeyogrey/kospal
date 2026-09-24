<?php

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;

return [

    'locales' => [
        'en' => 'English',
        'fr' => 'Français',
        'rn' => 'Kirundi',
        'rw' => 'Ikinyarwanda',
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
            'prices' => [
                'USD' => 20,
                'KES' => 2600,
            ],
            'max_branches' => 1,
            'max_staff' => 5,
            'features' => [
                'core',
            ],
        ],
        Plan::Pro->value => [
            'name' => 'Pro',
            'description' => 'Multi-branch retail with transfers, advanced reports, and CSV export.',
            'prices' => [
                'USD' => 40,
                'KES' => 5200,
            ],
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
            'prices' => [
                'USD' => 60,
                'KES' => 7800,
            ],
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

    /*
    |--------------------------------------------------------------------------
    | Offline payment instructions
    |--------------------------------------------------------------------------
    |
    | Shipped with the app so desktop installs can show how to pay without a
    | platform admin. Stored PlatformSetting values override these when present.
    |
    */

    'payment_instructions' => [
        'title' => env('KOSPAL_PAYMENT_TITLE', 'How to pay for your edition'),
        'body' => env(
            'KOSPAL_PAYMENT_BODY',
            "Pay using the bank or mobile money details below. Choosing an edition does not unlock it.\nSend your payment proof with the requested edition. Desktop shops also include the Machine ID and activate the matching license key. Web shops submit the transaction code for approval.",
        ),
        'bank_name' => env('KOSPAL_PAYMENT_BANK'),
        'account_name' => env('KOSPAL_PAYMENT_ACCOUNT_NAME'),
        'account_number' => env('KOSPAL_PAYMENT_ACCOUNT_NUMBER'),
        'mobile_money' => env('KOSPAL_PAYMENT_MOBILE_MONEY'),
        'support_note' => env('KOSPAL_PAYMENT_SUPPORT_NOTE'),
    ],

    'default_subscription_days' => (int) env('KOSPAL_DEFAULT_SUBSCRIPTION_DAYS', 30),

    'default_trial_days' => (int) env('KOSPAL_LICENSE_TRIAL_DAYS', 60),

    'invitation_expires_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | Currency conversion
    |--------------------------------------------------------------------------
    |
    | Plan list prices are stored in USD and KES. BIF is always derived from
    | KES at this fixed rate (Ksh 1 = 50 BIF).
    |
    */

    'exchange' => [
        'bif_per_kes' => (int) env('KOSPAL_BIF_PER_KES', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Referrals
    |--------------------------------------------------------------------------
    |
    | Desktop installs talk to the hosted KOSPAL app via server_url. Web mode
    | stores referrals locally and also serves /api/referrals/* for desktops.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Shop sync
    |--------------------------------------------------------------------------
    |
    | Desktop installs keep their own database and exchange changes with a
    | hosted KOSPAL office (KOSPAL_SYNC_SERVER_URL). The office database is the
    | shared shop: products, stock, and sales. Each computer keeps a working
    | copy so it can sell while offline, then sends those changes immediately.
    |
    */

    'sync' => [
        'server_url' => env('KOSPAL_SYNC_SERVER_URL'),
    ],

    'http_ca_bundle' => env('KOSPAL_HTTP_CA_BUNDLE'),

    'referral' => [
        'credit_percent' => 10,
        'code_expires_days' => 3,
        'qualify_after_days' => 7,
        'max_percent_per_payment' => 100,
        'server_url' => env('KOSPAL_REFERRAL_SERVER_URL'),
        'public_url' => env('KOSPAL_REFERRAL_PUBLIC_URL'),
    ],

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
