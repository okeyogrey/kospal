<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->bigInteger('unit_cost')->nullable()->after('quantity_after');
        });

        Schema::create('product_cost_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('previous_cost');
            $table->bigInteger('new_cost');
            $table->unsignedInteger('quantity_on_hand');
            $table->unsignedInteger('quantity_received')->nullable();
            $table->bigInteger('received_unit_cost')->nullable();
            $table->string('source')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'product_id', 'created_at'], 'product_cost_histories_lookup_index');
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'supplier_id']);
            $table->index(['business_id', 'branch_id']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->bigInteger('unit_cost');
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
        });

        Schema::create('goods_received_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'supplier_id']);
            $table->index(['business_id', 'branch_id']);
            $table->index(['business_id', 'purchase_order_id']);
        });

        Schema::create('goods_received_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost');
            $table->timestamps();

            $table->unique(['goods_received_note_id', 'product_id'], 'grn_items_grn_product_unique');
            $table->index(['business_id', 'product_id']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->string('supplier_invoice_number')->nullable();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_received_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->bigInteger('subtotal');
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total');
            $table->bigInteger('amount_paid')->default(0);
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'supplier_id']);
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost');
            $table->bigInteger('line_total');
            $table->timestamps();

            $table->index(['business_id', 'supplier_invoice_id']);
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->bigInteger('amount');
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('paid_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'supplier_id']);
            $table->index(['business_id', 'paid_at']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_invoice_id'], 'spa_payment_invoice_unique');
            $table->index(['business_id', 'supplier_invoice_id']);
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'branch_id']);
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->integer('variance')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('product_cost_histories');

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
