<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables that travel between linked shop computers.
     * Inventory balances are derived from stock movements and are not copied.
     *
     * @var list<string>
     */
    private array $uuidTables = [
        'users',
        'branches',
        'business_memberships',
        'branch_user',
        'categories',
        'expense_categories',
        'suppliers',
        'customers',
        'products',
        'product_packs',
        'product_supplier',
        'purchase_orders',
        'purchase_order_items',
        'goods_received_notes',
        'goods_received_note_items',
        'supplier_invoices',
        'supplier_invoice_items',
        'supplier_payments',
        'supplier_payment_allocations',
        'stock_transfers',
        'stock_transfer_items',
        'stock_counts',
        'stock_count_items',
        'stock_movements',
        'sales',
        'sale_items',
        'payments',
        'sale_returns',
        'sale_return_items',
        'customer_payments',
        'customer_payment_allocations',
        'customer_ledger_entries',
        'cash_sessions',
        'cash_movements',
        'expenses',
        'staff_shifts',
        'invitations',
        'product_cost_histories',
    ];

    public function up(): void
    {
        foreach ($this->uuidTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'public_uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->uuid('public_uuid')->nullable();

                if ($table === 'users') {
                    $blueprint->unique('public_uuid');

                    return;
                }

                $index = $table.'_public_uuid_unique';

                if (strlen($index) > 60) {
                    $index = 'u_'.substr(md5($table), 0, 16);
                }

                $blueprint->unique(['business_id', 'public_uuid'], $index);
            });
        }

        foreach ($this->uuidTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'public_uuid')) {
                continue;
            }

            $ids = DB::table($table)->whereNull('public_uuid')->pluck('id');

            foreach ($ids as $id) {
                DB::table($table)->where('id', $id)->update([
                    'public_uuid' => (string) Str::uuid(),
                ]);
            }
        }

        if (! Schema::hasColumn('sales', 'sync_conflict_reason')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->string('sync_conflict_reason')->nullable();
            });
        }

        if (! Schema::hasColumn('sales', 'sync_conflict_at')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->timestamp('sync_conflict_at')->nullable();
            });
        }

        if (! Schema::hasColumn('stock_movements', 'sync_local_only')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->boolean('sync_local_only')->default(false);
            });
        }

        Schema::create('sync_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('business_public_uuid')->unique();
            $table->string('business_name');
            $table->string('owner_email');
            $table->string('join_code', 12)->unique();
            $table->timestamps();
        });

        Schema::create('sync_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['sync_account_id', 'device_uuid']);
        });

        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->uuid('device_uuid');
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('op', 16);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sync_account_id', 'id']);
        });

        Schema::create('sync_rejections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('reason', 64);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sync_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_uuid');
            $table->uuid('product_uuid');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['sync_account_id', 'branch_uuid', 'product_uuid'], 'sync_stock_balance_unique');
        });

        Schema::create('sync_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('server_url');
            $table->text('token');
            $table->uuid('device_uuid');
            $table->string('join_code', 12)->nullable();
            $table->unsignedBigInteger('last_pulled_id')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique('business_id');
        });

        Schema::create('sync_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('op', 16);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('pushed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'pushed_at']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('reason', 64);
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('remote_uuid');
            $table->uuid('local_uuid');
            $table->timestamps();

            $table->unique(['business_id', 'remote_uuid']);
        });

        Schema::create('sync_deferred_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('server_operation_id');
            $table->json('body');
            $table->timestamps();

            $table->unique(['sync_link_id', 'server_operation_id'], 'sync_deferred_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_deferred_operations');
        Schema::dropIfExists('sync_identities');
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_outbox');
        Schema::dropIfExists('sync_links');
        Schema::dropIfExists('sync_stock');
        Schema::dropIfExists('sync_rejections');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('sync_devices');
        Schema::dropIfExists('sync_accounts');

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movements', 'sync_local_only')) {
                $table->dropColumn('sync_local_only');
            }
        });

        Schema::table('sales', function (Blueprint $table): void {
            if (Schema::hasColumn('sales', 'sync_conflict_at')) {
                $table->dropColumn('sync_conflict_at');
            }

            if (Schema::hasColumn('sales', 'sync_conflict_reason')) {
                $table->dropColumn('sync_conflict_reason');
            }
        });

        foreach ($this->uuidTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'public_uuid')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('public_uuid');
                });
            }
        }
    }
};
