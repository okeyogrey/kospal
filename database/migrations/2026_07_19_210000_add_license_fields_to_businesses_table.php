<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('license_activation_mode')->nullable()->after('subscription_ends_at');
            $table->text('license_key')->nullable()->after('license_activation_mode');
            $table->string('license_id')->nullable()->after('license_key');
            $table->string('licensed_machine_id')->nullable()->after('license_id');
            $table->timestamp('licensed_at')->nullable()->after('licensed_machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'license_activation_mode',
                'license_key',
                'license_id',
                'licensed_machine_id',
                'licensed_at',
            ]);
        });
    }
};
