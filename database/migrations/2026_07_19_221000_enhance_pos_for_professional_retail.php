<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('void_reason');
            $table->string('held_label')->nullable()->after('held_at');
            $table->foreignId('resumed_from_id')->nullable()->after('held_label')->constrained('sales')->nullOnDelete();
            $table->bigInteger('cash_tendered')->default(0)->after('total');
            $table->bigInteger('change_given')->default(0)->after('cash_tendered');
            $table->foreignId('approved_by')->nullable()->after('cashier_id')->constrained('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'branch_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->bigInteger('list_unit_price')->default(0)->after('unit_price');
            $table->bigInteger('unit_cost')->default(0)->after('list_unit_price');
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('tendered_amount')->nullable()->after('amount');
            $table->bigInteger('change_amount')->nullable()->after('tendered_amount');
        });

        Schema::table('sale_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('last_return_number')->default(0)->after('last_number');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number');
            $table->char('currency', 3);
            $table->bigInteger('total');
            $table->string('refund_method');
            $table->text('reason');
            $table->string('client_request_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'return_number']);
            $table->unique(['business_id', 'client_request_id']);
            $table->index(['business_id', 'sale_id']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('line_total');
            $table->bigInteger('unit_cost')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'sale_return_id']);
            $table->index(['business_id', 'sale_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');

        Schema::table('sale_sequences', function (Blueprint $table) {
            $table->dropColumn('last_return_number');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tendered_amount', 'change_amount']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['list_unit_price', 'unit_cost', 'returned_quantity']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'status', 'branch_id']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('resumed_from_id');
            $table->dropColumn([
                'held_at',
                'held_label',
                'cash_tendered',
                'change_given',
            ]);
        });
    }
};
