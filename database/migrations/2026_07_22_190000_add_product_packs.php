<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('base_unit_name', 40)->default('piece')->after('description');
        });

        Schema::create('product_packs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('units_per_pack');
            $table->string('barcode')->nullable();
            $table->bigInteger('selling_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'product_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('product_pack_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_packs')
                ->nullOnDelete();
            $table->unsignedInteger('pack_quantity')->nullable()->after('product_pack_id');
            $table->string('pack_name')->nullable()->after('pack_quantity');
        });

        Schema::table('goods_received_note_items', function (Blueprint $table): void {
            $table->foreignId('product_pack_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_packs')
                ->nullOnDelete();
            $table->unsignedInteger('pack_quantity')->nullable()->after('product_pack_id');
        });
    }

    public function down(): void
    {
        Schema::table('goods_received_note_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_pack_id');
            $table->dropColumn('pack_quantity');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_pack_id');
            $table->dropColumn(['pack_quantity', 'pack_name']);
        });

        Schema::dropIfExists('product_packs');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('base_unit_name');
        });
    }
};
