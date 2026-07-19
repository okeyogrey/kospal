<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('business_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->dropUnique(['business_id', 'name']);
            $table->index(['business_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
            $table->dropIndex(['business_id', 'parent_id', 'name']);
            $table->unique(['business_id', 'name']);
        });
    }
};
