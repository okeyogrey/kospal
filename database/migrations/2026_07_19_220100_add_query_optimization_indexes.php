<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive query indexes shared by SQLite desktop and MySQL/pgsql web installs.
 * Does not alter or remove existing migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->index(['subscription_status', 'subscription_ends_at'], 'businesses_subscription_expiry_index');
            $table->index(['is_active', 'plan'], 'businesses_active_plan_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['business_id', 'is_active', 'updated_at'], 'products_business_active_updated_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['business_id', 'is_active', 'name'], 'customers_business_active_name_index');
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->index(['business_id', 'quantity'], 'inventory_balances_business_quantity_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['current_business_id', 'current_branch_id'], 'users_current_workspace_index');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index(['business_id', 'status', 'updated_at'], 'stock_transfers_business_status_updated_index');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex('businesses_subscription_expiry_index');
            $table->dropIndex('businesses_active_plan_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_business_active_updated_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_business_active_name_index');
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropIndex('inventory_balances_business_quantity_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_current_workspace_index');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('stock_transfers_business_status_updated_index');
        });
    }
};
