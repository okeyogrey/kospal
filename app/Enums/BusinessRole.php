<?php

namespace App\Enums;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case InventoryClerk = 'inventory_clerk';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    public function canManageStaff(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canManageBranches(): bool
    {
        return $this === self::Owner;
    }

    public function canManageSubscription(): bool
    {
        return $this === self::Owner;
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::InventoryClerk], true);
    }

    public function canAccessSales(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier], true);
    }

    public function canApplySaleDiscount(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canVoidSale(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canCreateCustomers(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier], true);
    }

    public function canManageExpenses(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Roles that may always log expenses (cashier requires a business setting).
     */
    public function canLogExpensesByDefault(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::InventoryClerk], true);
    }

    public function canManageExpenseCategories(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canViewReports(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function requiresBranchAssignment(): bool
    {
        return in_array($this, [self::Cashier, self::InventoryClerk], true);
    }

    public function canClockShifts(): bool
    {
        return in_array($this, [self::Manager, self::Cashier, self::InventoryClerk], true);
    }

    public function canMonitorShifts(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Roles a manager may assign (never owner).
     *
     * @return list<self>
     */
    public static function assignableByManager(): array
    {
        return [self::Manager, self::Cashier, self::InventoryClerk];
    }
}
