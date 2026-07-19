<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'category_id' => function (array $attributes) {
                $businessId = $attributes['business_id'];

                $root = Category::factory()->create([
                    'business_id' => $businessId,
                    'name' => fake()->unique()->words(2, true).' Root',
                ]);

                $sub = Category::factory()->childOf($root)->create([
                    'name' => fake()->unique()->words(2, true).' Sub',
                ]);

                $leaf = Category::factory()->childOf($sub)->create([
                    'name' => fake()->unique()->words(2, true).' Leaf',
                ]);

                return $leaf->id;
            },
            'name' => fake()->unique()->company(),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn () => [
            'business_id' => $business->id,
        ]);
    }
}
