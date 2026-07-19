<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('business_id')
                ->constrained()
                ->nullOnDelete();

            $table->dropForeign(['category_id']);
            $table->dropIndex(['business_id', 'category_id']);
            $table->dropColumn('category_id');

            $table->index(['business_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('business_id')
                ->constrained()
                ->nullOnDelete();

            $table->dropForeign(['brand_id']);
            $table->dropIndex(['business_id', 'brand_id']);
            $table->dropColumn('brand_id');

            $table->index(['business_id', 'category_id']);
        });
    }
};
