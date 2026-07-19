<?php

namespace App\Support\Catalog;

use App\Models\Business;
use App\Models\Category;

final class CatalogTaxonomy
{
    /**
     * @return array{
     *     categories: list<array{id: int, name: string}>,
     * }
     */
    public static function forBusiness(Business $business): array
    {
        $categories = Category::query()
            ->forBusiness($business)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
        ];
    }
}
