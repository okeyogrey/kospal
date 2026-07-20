<?php

namespace App\Support\FeatureFlags;

/**
 * Canonical feature identifiers used by FeatureFlagService implementations.
 */
final class Features
{
    public const CORE = 'core';

    public const ADVANCED_REPORTS = 'advanced_reports';

    public const CSV_EXPORT = 'csv_export';

    public const CSV_IMPORT = 'csv_import';

    public const EXCEL_IMPORT = 'excel_import';

    public const EXCEL_EXPORT = 'excel_export';

    public const PDF_REPORTS = 'pdf_reports';

    public const STOCK_TRANSFERS = 'stock_transfers';

    public const PURCHASE_ORDERS = 'purchase_orders';

    public const STOCK_COUNTS = 'stock_counts';

    public const AUDIT_LOGS = 'audit_logs';

    public const CONSOLIDATED_REPORTS = 'consolidated_reports';

    public const CUSTOMER_CREDIT = 'customer_credit';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CORE,
            self::ADVANCED_REPORTS,
            self::CSV_EXPORT,
            self::CSV_IMPORT,
            self::EXCEL_IMPORT,
            self::EXCEL_EXPORT,
            self::PDF_REPORTS,
            self::STOCK_TRANSFERS,
            self::PURCHASE_ORDERS,
            self::STOCK_COUNTS,
            self::AUDIT_LOGS,
            self::CONSOLIDATED_REPORTS,
            self::CUSTOMER_CREDIT,
        ];
    }
}
