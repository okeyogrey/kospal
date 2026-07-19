<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->string('sale_number');
            $table->string('status');
            $table->string('payment_method');
            $table->char('currency', 3);
            $table->bigInteger('subtotal');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('total');
            $table->string('customer_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('client_request_id', 64)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'sale_number']);
            $table->unique(['business_id', 'client_request_id']);
            $table->index(['business_id', 'branch_id', 'created_at']);
            $table->index(['business_id', 'cashier_id']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'payment_method']);
            $table->index(['business_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
