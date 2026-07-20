<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(100, 50000);
        $selling = $cost + fake()->numberBetween(50, 25000);

        return [
            'business_id' => Business::factory(),
            'category_id' => null,
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'barcode' => fake()->optional()->ean13(),
            'description' => fake()->optional()->sentence(),
            'cost_price' => $cost,
            'selling_price' => $selling,
            'min_selling_price' => $cost,
            'is_negotiable' => true,
            'reorder_level' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function nonNegotiable(): static
    {
        return $this->state(fn () => ['is_negotiable' => false]);
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn () => [
            'business_id' => $business->id,
        ]);
    }

    public function withCategory(?Category $category = null): static
    {
        return $this->state(function (array $attributes) use ($category) {
            $category ??= Category::factory()->create([
                'business_id' => $attributes['business_id'],
            ]);

            return ['category_id' => $category->id];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            if ($product->min_selling_price <= 0 || $product->min_selling_price > $product->selling_price) {
                $product->min_selling_price = min($product->cost_price, $product->selling_price);
            }
        });
    }
}
