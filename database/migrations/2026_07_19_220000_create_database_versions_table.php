<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Application schema version history (portable across SQLite / MySQL / pgsql).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('schema_version');
            $table->string('driver', 32);
            $table->string('app_version', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['schema_version', 'created_at']);
        });

        DB::table('database_versions')->insert([
            'schema_version' => (int) config('deployment.database.schema_version', 1),
            'driver' => Schema::getConnection()->getDriverName(),
            'app_version' => (string) config('deployment.version'),
            'notes' => 'Initial database version tracking',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('database_versions');
    }
};
