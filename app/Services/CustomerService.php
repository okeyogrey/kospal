<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\AuditLogger;

class CustomerService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): Customer
    {
        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log(
            action: 'customer.created',
            auditable: $customer,
            actor: $actor,
            businessId: $business->id,
        );

        return $customer;
    }

    /**
     * @param  array{
     *     name?: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(Customer $customer, array $data, User $actor): Customer
    {
        $customer->update($data);

        $this->audit->log(
            action: 'customer.updated',
            auditable: $customer,
            metadata: $data,
            actor: $actor,
            businessId: $customer->business_id,
        );

        return $customer->refresh();
    }

    public function delete(Customer $customer, User $actor): void
    {
        $businessId = $customer->business_id;
        $payload = ['customer_id' => $customer->id, 'name' => $customer->name];
        $customer->delete();

        $this->audit->log(
            action: 'customer.deleted',
            metadata: $payload,
            actor: $actor,
            businessId: $businessId,
        );
    }
}
