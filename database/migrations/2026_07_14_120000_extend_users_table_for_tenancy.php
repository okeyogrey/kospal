<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('preferred_locale', 5)->nullable()->after('phone');
            $table->boolean('is_platform_super_admin')->default(false)->after('remember_token');
            $table->unsignedBigInteger('current_business_id')->nullable()->after('is_platform_super_admin');
            $table->unsignedBigInteger('current_branch_id')->nullable()->after('current_business_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'preferred_locale',
                'is_platform_super_admin',
                'current_business_id',
                'current_branch_id',
            ]);
        });
    }
};
