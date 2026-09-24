<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table): void {
            $table->boolean('materializes')->default(false);
            $table->unsignedBigInteger('materialized_operation_id')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table): void {
            $table->dropColumn(['materializes', 'materialized_operation_id']);
        });
    }
};
