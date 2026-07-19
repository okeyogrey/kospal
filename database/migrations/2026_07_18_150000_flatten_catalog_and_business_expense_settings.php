<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('cashiers_can_log_expenses')->default(false)->after('is_active');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('business_id')
                ->constrained('categories')
                ->nullOnDelete();
        });

        $this->migrateProductsToRootCategories();

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropIndex(['business_id', 'brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('brands');

        // Point nested-category products at their root, then remove nested rows.
        $this->reassignNestedCategoriesToRoots();

        DB::table('categories')->whereNotNull('parent_id')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['business_id', 'parent_id', 'name']);
            $table->dropColumn('parent_id');
            $table->unique(['business_id', 'name']);
        });

        DB::table('stock_movements')
            ->where('type', 'opening_stock')
            ->update(['type' => 'stock_receipt']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('business_id')
                ->constrained('categories')
                ->nullOnDelete();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'category_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('business_id')
                ->constrained('brands')
                ->nullOnDelete();
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('cashiers_can_log_expenses');
        });

        DB::table('stock_movements')
            ->where('type', 'stock_receipt')
            ->update(['type' => 'opening_stock']);
    }

    protected function migrateProductsToRootCategories(): void
    {
        if (! Schema::hasTable('brands')) {
            return;
        }

        $categories = DB::table('categories')->get(['id', 'parent_id'])->keyBy('id');
        $brands = DB::table('brands')->get(['id', 'category_id'])->keyBy('id');

        foreach (DB::table('products')->whereNotNull('brand_id')->cursor() as $product) {
            $brand = $brands->get($product->brand_id);

            if ($brand === null) {
                continue;
            }

            $rootId = $this->rootCategoryId((int) $brand->category_id, $categories);

            if ($rootId !== null) {
                DB::table('products')->where('id', $product->id)->update([
                    'category_id' => $rootId,
                ]);
            }
        }
    }

    protected function reassignNestedCategoriesToRoots(): void
    {
        $categories = DB::table('categories')->get(['id', 'parent_id'])->keyBy('id');

        foreach (DB::table('products')->whereNotNull('category_id')->cursor() as $product) {
            $rootId = $this->rootCategoryId((int) $product->category_id, $categories);

            if ($rootId !== null && $rootId !== (int) $product->category_id) {
                DB::table('products')->where('id', $product->id)->update([
                    'category_id' => $rootId,
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $categories
     */
    protected function rootCategoryId(int $categoryId, $categories): ?int
    {
        $current = $categories->get($categoryId);
        $guard = 0;

        while ($current !== null && $current->parent_id !== null && $guard < 10) {
            $current = $categories->get($current->parent_id);
            $guard++;
        }

        return $current?->id !== null ? (int) $current->id : null;
    }
};
