<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('operating_mode')->default('always_open')->after('timezone');
            $table->time('opens_at')->nullable()->after('operating_mode');
            $table->time('closes_at')->nullable()->after('opens_at');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('operating_mode')->nullable()->after('phone');
            $table->time('opens_at')->nullable()->after('operating_mode');
            $table->time('closes_at')->nullable()->after('opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['operating_mode', 'opens_at', 'closes_at']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['operating_mode', 'opens_at', 'closes_at']);
        });
    }
};
