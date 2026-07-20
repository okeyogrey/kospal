<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoodsReceivedNoteController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Platform\BusinessSubscriptionController;
use App\Http\Controllers\Platform\PaymentInstructionsController;
use App\Http\Controllers\Platform\SubscriptionRequestController as PlatformSubscriptionRequestController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductivityController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffShiftController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('locale.update');

Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.accept.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('onboarding.store');

    Route::post('invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
        ->middleware('throttle:invitations')
        ->name('invitations.accept');

    Route::middleware(['business', 'subscription.write'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])
            ->middleware('throttle:exports')
            ->name('reports.export');
        Route::get('reports/{report}/export-pdf', [ReportController::class, 'exportPdf'])
            ->middleware('throttle:exports')
            ->name('reports.export-pdf');

        Route::get('productivity', [ProductivityController::class, 'index'])->name('productivity.index');
        Route::post('productivity/import', [ProductivityController::class, 'import'])
            ->middleware('throttle:exports')
            ->name('productivity.import');
        Route::get('productivity/{entity}/export/{format}', [ProductivityController::class, 'export'])
            ->middleware('throttle:exports')
            ->name('productivity.export');
        Route::get('productivity/{entity}/template/{format}', [ProductivityController::class, 'template'])
            ->name('productivity.template');

        Route::inertia('unauthorized', 'unauthorized')->name('unauthorized');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('expenses.store');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::post('expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('expenses.destroy');

        Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])
            ->name('expense-categories.index');
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('expense-categories.store');
        Route::patch('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('expense-categories.update');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('expense-categories.destroy');

        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
            ->middleware('throttle:attachments')
            ->name('attachments.download');

        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/pos', [SaleController::class, 'pos'])->name('sales.pos');
        Route::get('sales/pos/products', [SaleController::class, 'searchProducts'])
            ->middleware('throttle:60,1')
            ->name('sales.pos.products');
        Route::get('sales/pos/barcode', [SaleController::class, 'lookupBarcode'])
            ->middleware('throttle:120,1')
            ->name('sales.pos.barcode');
        Route::get('sales/held', [SaleController::class, 'held'])->name('sales.held');
        Route::post('sales/hold', [SaleController::class, 'hold'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.hold');
        Route::post('sales/held/{sale}/resume', [SaleController::class, 'resume'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.held.resume');
        Route::delete('sales/held/{sale}', [SaleController::class, 'discardHeld'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.held.discard');
        Route::post('sales', [SaleController::class, 'store'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.store');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('sales/{sale}/void', [SaleController::class, 'void'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.void');
        Route::post('sales/{sale}/returns', [SaleController::class, 'returnItems'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('sales.returns');
        Route::post('sales/{sale}/receipt/reprint', [SaleController::class, 'reprint'])
            ->middleware('throttle:60,1')
            ->name('sales.receipt.reprint');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('sales/{sale}/invoice.pdf', [SaleController::class, 'invoice'])->name('sales.invoice');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/search', [CustomerController::class, 'search'])
            ->middleware('throttle:60,1')
            ->name('customers.search');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/statement.pdf', [CustomerController::class, 'statement'])
            ->name('customers.statement');
        Route::post('customers', [CustomerController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('customers.store');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('customers.destroy');

        Route::post('workspace/branch', [WorkspaceController::class, 'switchBranch'])
            ->name('workspace.branch');
        Route::post('workspace/business', [WorkspaceController::class, 'switchBusiness'])
            ->name('workspace.business');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('categories.store');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('categories.destroy');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('suppliers.store');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('suppliers.destroy');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::patch('products/{product}', [ProductController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('products.destroy');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('inventory/timeline', [InventoryController::class, 'timeline'])->name('inventory.timeline');
        Route::get('inventory/valuation', [InventoryController::class, 'valuation'])->name('inventory.valuation');
        Route::post('inventory/receive-stock', [InventoryController::class, 'storeReceiveStock'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('inventory.receive-stock.store');
        Route::post('inventory/adjustments', [InventoryController::class, 'storeAdjustment'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('inventory.adjustments.store');

        Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::get('stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('stock-transfers.store');
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
        Route::post('stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])
            ->middleware('throttle:sensitive')
            ->name('stock-transfers.dispatch');
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])
            ->middleware('throttle:sensitive')
            ->name('stock-transfers.receive');
        Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
            ->middleware('throttle:sensitive')
            ->name('stock-transfers.cancel');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('purchase-orders.store');
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
            ->middleware('throttle:sensitive')
            ->name('purchase-orders.send');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('throttle:sensitive')
            ->name('purchase-orders.cancel');

        Route::get('goods-received', [GoodsReceivedNoteController::class, 'index'])->name('goods-received.index');
        Route::get('goods-received/create', [GoodsReceivedNoteController::class, 'create'])->name('goods-received.create');
        Route::post('goods-received', [GoodsReceivedNoteController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('goods-received.store');
        Route::get('goods-received/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'show'])->name('goods-received.show');
        Route::post('goods-received/{goodsReceivedNote}/post', [GoodsReceivedNoteController::class, 'post'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('goods-received.post');
        Route::post('goods-received/{goodsReceivedNote}/cancel', [GoodsReceivedNoteController::class, 'cancel'])
            ->middleware('throttle:sensitive')
            ->name('goods-received.cancel');

        Route::get('supplier-invoices', [SupplierInvoiceController::class, 'index'])->name('supplier-invoices.index');
        Route::get('supplier-invoices/create', [SupplierInvoiceController::class, 'create'])->name('supplier-invoices.create');
        Route::post('supplier-invoices', [SupplierInvoiceController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('supplier-invoices.store');
        Route::get('supplier-invoices/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('supplier-invoices.show');
        Route::post('supplier-invoices/{supplierInvoice}/post', [SupplierInvoiceController::class, 'post'])
            ->middleware('throttle:sensitive')
            ->name('supplier-invoices.post');
        Route::post('supplier-invoices/{supplierInvoice}/void', [SupplierInvoiceController::class, 'void'])
            ->middleware('throttle:sensitive')
            ->name('supplier-invoices.void');

        Route::get('supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
        Route::get('supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
        Route::post('supplier-payments', [SupplierPaymentController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('supplier-payments.store');
        Route::get('supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->name('supplier-payments.show');

        Route::get('customer-payments', [CustomerPaymentController::class, 'index'])->name('customer-payments.index');
        Route::get('customer-payments/create', [CustomerPaymentController::class, 'create'])->name('customer-payments.create');
        Route::post('customer-payments', [CustomerPaymentController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('customer-payments.store');
        Route::get('customer-payments/{customerPayment}', [CustomerPaymentController::class, 'show'])->name('customer-payments.show');

        Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::get('stock-counts/create', [StockCountController::class, 'create'])->name('stock-counts.create');
        Route::post('stock-counts', [StockCountController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('stock-counts.store');
        Route::get('stock-counts/{stockCount}', [StockCountController::class, 'show'])->name('stock-counts.show');
        Route::post('stock-counts/{stockCount}/start', [StockCountController::class, 'start'])
            ->middleware('throttle:sensitive')
            ->name('stock-counts.start');
        Route::post('stock-counts/{stockCount}/record', [StockCountController::class, 'record'])
            ->middleware('throttle:sensitive')
            ->name('stock-counts.record');
        Route::post('stock-counts/{stockCount}/complete', [StockCountController::class, 'complete'])
            ->middleware(['throttle:sensitive', 'shift.active'])
            ->name('stock-counts.complete');
        Route::post('stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])
            ->middleware('throttle:sensitive')
            ->name('stock-counts.cancel');

        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])
            ->middleware('throttle:sensitive')
            ->name('branches.store');
        Route::patch('branches/{branch}', [BranchController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('branches.destroy');
        Route::patch('business/operating-hours', [BranchController::class, 'updateOperatingHours'])
            ->middleware('throttle:sensitive')
            ->name('business.operating-hours.update');

        Route::get('shifts', [StaffShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts/clock-in', [StaffShiftController::class, 'clockIn'])
            ->middleware('throttle:sensitive')
            ->name('shifts.clock-in');
        Route::post('shifts/clock-out', [StaffShiftController::class, 'clockOut'])
            ->middleware('throttle:sensitive')
            ->name('shifts.clock-out');
        Route::get('shifts/{shift}', [StaffShiftController::class, 'show'])->name('shifts.show');
        Route::post('shifts/{shift}/force-close', [StaffShiftController::class, 'forceClose'])
            ->middleware('throttle:sensitive')
            ->name('shifts.force-close');

        Route::get('cash-sessions', [\App\Http\Controllers\CashSessionController::class, 'index'])->name('cash-sessions.index');
        Route::post('cash-sessions/open', [\App\Http\Controllers\CashSessionController::class, 'open'])
            ->middleware('throttle:sensitive')
            ->name('cash-sessions.open');
        Route::get('cash-sessions/{cashSession}', [\App\Http\Controllers\CashSessionController::class, 'show'])->name('cash-sessions.show');
        Route::post('cash-sessions/{cashSession}/movements', [\App\Http\Controllers\CashSessionController::class, 'storeMovement'])
            ->middleware('throttle:sensitive')
            ->name('cash-sessions.movements.store');
        Route::post('cash-sessions/{cashSession}/close', [\App\Http\Controllers\CashSessionController::class, 'close'])
            ->middleware('throttle:sensitive')
            ->name('cash-sessions.close');
        Route::get('cash-sessions/{cashSession}/z-report.pdf', [\App\Http\Controllers\CashSessionController::class, 'zReport'])
            ->name('cash-sessions.z-report');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::patch('staff/settings', [StaffController::class, 'updateSettings'])
            ->middleware('throttle:sensitive')
            ->name('staff.settings.update');
        Route::post('staff/invitations', [StaffController::class, 'invite'])
            ->middleware('throttle:invitations')
            ->name('staff.invitations.store');
        Route::delete('staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])
            ->middleware('throttle:invitations')
            ->name('staff.invitations.destroy');
        Route::patch('staff/memberships/{membership}', [StaffController::class, 'updateMembership'])
            ->middleware('throttle:sensitive')
            ->name('staff.memberships.update');
        Route::delete('staff/memberships/{membership}', [StaffController::class, 'deactivateMembership'])
            ->middleware('throttle:sensitive')
            ->name('staff.memberships.destroy');

        Route::get('subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('subscription/requests', [SubscriptionController::class, 'storeRequest'])
            ->middleware('throttle:sensitive')
            ->name('subscription.requests.store');
    });

    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::get('subscription-requests', [PlatformSubscriptionRequestController::class, 'index'])
            ->name('subscription-requests.index');
        Route::post('subscription-requests/{subscriptionRequest}/approve', [PlatformSubscriptionRequestController::class, 'approve'])
            ->middleware('throttle:sensitive')
            ->name('subscription-requests.approve');
        Route::post('subscription-requests/{subscriptionRequest}/reject', [PlatformSubscriptionRequestController::class, 'reject'])
            ->middleware('throttle:sensitive')
            ->name('subscription-requests.reject');

        Route::get('payment-instructions', [PaymentInstructionsController::class, 'edit'])
            ->name('payment-instructions.edit');
        Route::put('payment-instructions', [PaymentInstructionsController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('payment-instructions.update');

        Route::patch('businesses/{business}/subscription', [BusinessSubscriptionController::class, 'update'])
            ->middleware('throttle:sensitive')
            ->name('businesses.subscription.update');
    });
});

require __DIR__.'/settings.php';
