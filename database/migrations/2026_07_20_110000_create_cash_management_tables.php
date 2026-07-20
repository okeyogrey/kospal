<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->bigInteger('opening_float')->default(0);
            $table->text('opening_notes')->nullable();
            $table->timestamp('opened_at');
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('counted_cash')->nullable();
            $table->bigInteger('closing_float_left')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->text('variance_reason')->nullable();
            $table->foreignId('variance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('z_report_snapshot')->nullable();
            $table->timestamps();

            $table->unique('staff_shift_id');
            $table->index(['business_id', 'branch_id', 'status']);
            $table->index(['business_id', 'closed_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['cash_session_id', 'type']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('staff_shift_id')->nullable()->after('cashier_id')->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->after('staff_shift_id')->constrained()->nullOnDelete();

            $table->index(['cash_session_id', 'status']);
            $table->index(['staff_shift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
            $table->dropConstrainedForeignId('staff_shift_id');
        });

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
