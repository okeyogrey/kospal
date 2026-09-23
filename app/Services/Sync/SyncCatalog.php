<?php

namespace App\Services\Sync;

use App\Models\Branch;
use App\Models\BranchAssignment;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Invitation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductPack;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StaffShift;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class SyncCatalog
{
    /** @var array<string, bool>|null */
    private static ?array $uuidColumns = null;

    /** @var array<string, class-string<Model>>|null */
    private static ?array $byTable = null;

    /**
     * Dependency order used when a shop is first linked.
     *
     * @return list<class-string<Model>>
     */
    public static function models(): array
    {
        return [
            User::class,
            Business::class,
            Branch::class,
            BusinessMembership::class,
            BranchAssignment::class,
            Category::class,
            ExpenseCategory::class,
            Supplier::class,
            Customer::class,
            Product::class,
            ProductPack::class,
            ProductSupplier::class,
            PurchaseOrder::class,
            PurchaseOrderItem::class,
            GoodsReceivedNote::class,
            GoodsReceivedNoteItem::class,
            SupplierInvoice::class,
            SupplierInvoiceItem::class,
            SupplierPayment::class,
            SupplierPaymentAllocation::class,
            StockTransfer::class,
            StockTransferItem::class,
            StockCount::class,
            StockCountItem::class,
            Sale::class,
            SaleItem::class,
            Payment::class,
            SaleReturn::class,
            SaleReturnItem::class,
            CustomerPayment::class,
            CustomerPaymentAllocation::class,
            CustomerLedgerEntry::class,
            CashSession::class,
            CashMovement::class,
            Expense::class,
            StaffShift::class,
            Invitation::class,
            ProductCostHistory::class,
            StockMovement::class,
        ];
    }

    /**
     * Integer foreign keys rewritten to public UUIDs on the wire.
     *
     * @return array<string, class-string<Model>>
     */
    public static function foreignKeys(): array
    {
        return [
            'branch_id' => Branch::class,
            'source_branch_id' => Branch::class,
            'destination_branch_id' => Branch::class,
            'user_id' => User::class,
            'cashier_id' => User::class,
            'owner_user_id' => User::class,
            'created_by' => User::class,
            'voided_by' => User::class,
            'approved_by' => User::class,
            'posted_by' => User::class,
            'sent_by' => User::class,
            'cancelled_by' => User::class,
            'received_by' => User::class,
            'dispatched_by' => User::class,
            'completed_by' => User::class,
            'clocked_out_by' => User::class,
            'variance_approved_by' => User::class,
            'closed_by' => User::class,
            'recorded_by' => User::class,
            'invited_by_user_id' => User::class,
            'accepted_user_id' => User::class,
            'category_id' => Category::class,
            'product_id' => Product::class,
            'product_pack_id' => ProductPack::class,
            'supplier_id' => Supplier::class,
            'customer_id' => Customer::class,
            'sale_id' => Sale::class,
            'sale_item_id' => SaleItem::class,
            'sale_return_id' => SaleReturn::class,
            'resumed_from_id' => Sale::class,
            'expense_category_id' => ExpenseCategory::class,
            'purchase_order_id' => PurchaseOrder::class,
            'purchase_order_item_id' => PurchaseOrderItem::class,
            'goods_received_note_id' => GoodsReceivedNote::class,
            'supplier_invoice_id' => SupplierInvoice::class,
            'supplier_payment_id' => SupplierPayment::class,
            'stock_transfer_id' => StockTransfer::class,
            'stock_count_id' => StockCount::class,
            'cash_session_id' => CashSession::class,
            'staff_shift_id' => StaffShift::class,
            'customer_payment_id' => CustomerPayment::class,
        ];
    }

    /**
     * @return list<string>
     */
    public static function excludedAttributes(): array
    {
        return [
            'id',
            'business_id',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'license_key',
            'license_id',
            'licensed_machine_id',
            'licensed_at',
            'license_activation_mode',
            'plan',
            'subscription_status',
            'subscription_ends_at',
            'max_staff_override',
            'current_business_id',
            'current_branch_id',
            'sync_local_only',
            'password',
        ];
    }

    /**
     * @return list<string>
     */
    public static function businessFields(): array
    {
        return [
            'name',
            'country',
            'currency',
            'timezone',
            'operating_mode',
            'opens_at',
            'closes_at',
            'default_locale',
            'cashiers_can_log_expenses',
            'cashiers_can_approve_price_overrides',
            'is_active',
        ];
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classForTable(string $table): ?string
    {
        if (self::$byTable === null) {
            self::$byTable = [];

            foreach (self::models() as $class) {
                self::$byTable[(new $class)->getTable()] = $class;
            }
        }

        return self::$byTable[$table] ?? null;
    }

    public static function hasPublicUuidColumn(string $table): bool
    {
        if (self::$uuidColumns === null) {
            self::$uuidColumns = [];
        }

        if (! array_key_exists($table, self::$uuidColumns)) {
            self::$uuidColumns[$table] = Schema::hasTable($table)
                && Schema::hasColumn($table, 'public_uuid');
        }

        return self::$uuidColumns[$table];
    }
}
