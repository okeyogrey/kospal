<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('expense_date');
            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->string('payee');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'expense_date']);
            $table->index(['business_id', 'branch_id', 'expense_date']);
            $table->index(['business_id', 'expense_category_id']);
            $table->index(['business_id', 'payee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
