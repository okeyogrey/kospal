<?php

namespace App\Enums;

enum ImportEntity: string
{
    case Products = 'products';
    case Customers = 'customers';

    public function label(): string
    {
        return match ($this) {
            self::Products => 'Products',
            self::Customers => 'Customers',
        };
    }

    /**
     * @return list<string>
     */
    public function requiredColumns(): array
    {
        return match ($this) {
            self::Products => ['name', 'sku', 'cost_price', 'selling_price'],
            self::Customers => ['name'],
        };
    }

    /**
     * @return list<string>
     */
    public function optionalColumns(): array
    {
        return match ($this) {
            self::Products => [
                'barcode',
                'category',
                'description',
                'min_selling_price',
                'is_negotiable',
                'reorder_level',
                'is_active',
            ],
            self::Customers => [
                'phone',
                'email',
                'address',
                'notes',
                'is_active',
                'credit_enabled',
                'credit_limit',
                'payment_terms_days',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function allColumns(): array
    {
        return [...$this->requiredColumns(), ...$this->optionalColumns()];
    }
}
