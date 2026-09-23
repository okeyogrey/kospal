<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('slug');
            $table->timestamp('first_paid_period_at')->nullable()->after('licensed_at');
        });

        if (Schema::hasTable('businesses')) {
            DB::table('businesses')
                ->whereNull('public_uuid')
                ->orderBy('id')
                ->each(function (object $business): void {
                    DB::table('businesses')
                        ->where('id', $business->id)
                        ->update(['public_uuid' => (string) Str::uuid()]);
                });
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->unique('public_uuid');
        });

        Schema::create('referral_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_email');
            $table->string('business_name');
            $table->string('machine_id')->nullable();
            $table->string('api_token_hash')->nullable();
            $table->text('remote_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('first_paid_period_at')->nullable();
            $table->unsignedTinyInteger('cached_available_percent')->default(0);
            $table->timestamps();

            $table->index('owner_email');
            $table->index('machine_id');
        });

        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_account_id')->constrained('referral_accounts')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['referral_account_id', 'expires_at']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->nullable()->constrained('referral_codes')->nullOnDelete();
            $table->foreignId('referrer_account_id')->constrained('referral_accounts')->cascadeOnDelete();
            $table->foreignId('referred_account_id')->constrained('referral_accounts')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('onboarded_at');
            $table->timestamp('qualifies_at');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('referred_account_id');
            $table->index(['status', 'qualifies_at']);
            $table->index('referrer_account_id');
        });

        Schema::create('referral_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('referral_account_id')->constrained('referral_accounts')->cascadeOnDelete();
            $table->string('side');
            $table->unsignedTinyInteger('percent');
            $table->unsignedTinyInteger('remaining_percent');
            $table->string('status');
            $table->boolean('first_payment_only')->default(false);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['referral_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_credits');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referral_accounts');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn(['public_uuid', 'first_paid_period_at']);
        });
    }
};
