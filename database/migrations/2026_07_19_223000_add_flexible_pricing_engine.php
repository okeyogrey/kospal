<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('min_selling_price')->default(0)->after('selling_price');
            $table->boolean('is_negotiable')->default(true)->after('min_selling_price');
        });

        DB::table('products')->update([
            'min_selling_price' => DB::raw('cost_price'),
        ]);

        Schema::table('business_memberships', function (Blueprint $table) {
            $table->unsignedTinyInteger('negotiation_floor_percent')->default(100)->after('role');
            $table->string('approval_pin')->nullable()->after('negotiation_floor_percent');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->bigInteger('negotiated_difference')->default(0)->after('unit_cost');
            $table->bigInteger('profit')->default(0)->after('negotiated_difference');
            $table->integer('margin_bps')->default(0)->after('profit');
            $table->boolean('manager_approved')->default(false)->after('margin_bps');
        });

        DB::table('sale_items')->update([
            'negotiated_difference' => DB::raw('list_unit_price - unit_price'),
            'profit' => DB::raw('(unit_price - unit_cost) * quantity'),
            'margin_bps' => DB::raw('CASE WHEN unit_price > 0 THEN CAST(((unit_price - unit_cost) * 10000) / unit_price AS SIGNED) ELSE 0 END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'negotiated_difference',
                'profit',
                'margin_bps',
                'manager_approved',
            ]);
        });

        Schema::table('business_memberships', function (Blueprint $table) {
            $table->dropColumn(['negotiation_floor_percent', 'approval_pin']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_selling_price', 'is_negotiable']);
        });
    }
};
