<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('credit_enabled')->default(false)->after('is_active');
            $table->bigInteger('credit_limit')->nullable()->after('credit_enabled');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('credit_limit');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->bigInteger('amount_paid')->default(0)->after('total');
        });

        DB::table('sales')
            ->where('status', 'completed')
            ->update(['amount_paid' => DB::raw('total')]);

        Schema::create('customer_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->bigInteger('amount');
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('paid_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'customer_id']);
            $table->index(['business_id', 'paid_at']);
        });

        Schema::create('customer_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['customer_payment_id', 'sale_id'], 'cpa_payment_sale_unique');
            $table->index(['business_id', 'sale_id']);
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('direction');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('description');
            $table->nullableMorphs('reference');
            $table->date('entry_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'customer_id', 'entry_date'], 'customer_ledger_lookup_index');
            $table->index(['business_id', 'customer_id', 'id'], 'customer_ledger_balance_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('amount_paid');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['credit_enabled', 'credit_limit', 'payment_terms_days']);
        });
    }
};
