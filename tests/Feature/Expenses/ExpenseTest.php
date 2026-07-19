<?php

use App\Enums\BusinessRole;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    Storage::fake('attachments');
    $this->withoutVite();
});

it('allows owners to create list view edit and delete expenses with audit logs', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);
    $category = ExpenseCategory::factory()->create([
        'business_id' => $business->id,
        'name' => 'Rent',
    ]);

    $this->actingAs($owner)
        ->post(route('expenses.store'), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '2026-07-01',
            'amount' => '1250.50',
            'payee' => 'City Power',
            'description' => 'July electricity',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $expense = Expense::query()->forBusiness($business)->first();
    expect($expense)->not->toBeNull()
        ->and($expense->amount)->toBe(Money::toMinor('1250.50', 'KES'))
        ->and($expense->payee)->toBe('City Power')
        ->and($expense->receipt)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'expense.created')->where('business_id', $business->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'expense.attachment_added')->where('business_id', $business->id)->exists())->toBeTrue();

    Storage::disk('attachments')->assertExists($expense->receipt->path);

    $this->actingAs($owner)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.payee', 'City Power'));

    $this->actingAs($owner)
        ->get(route('expenses.show', $expense))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/show')
            ->where('expense.payee', 'City Power')
            ->has('activity'));

    $this->actingAs($owner)
        ->post(route('expenses.update', $expense), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '2026-07-02',
            'amount' => '1300.00',
            'payee' => 'City Power Ltd',
            'description' => 'Updated bill',
        ])
        ->assertRedirect(route('expenses.show', $expense));

    expect($expense->fresh()->payee)->toBe('City Power Ltd');
    expect(AuditLog::query()->where('action', 'expense.updated')->where('business_id', $business->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('expenses.index'));

    expect(Expense::query()->forBusiness($business)->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'expense.deleted')->where('business_id', $business->id)->exists())->toBeTrue();
});

it('denies cashiers from expense routes by default', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);
    $expense = Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'created_by' => $cashier->id,
        'currency' => $business->currency,
    ]);

    $this->actingAs($cashier)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('expenses.create'))->assertForbidden();
    $this->actingAs($cashier)->post(route('expenses.store'), [
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'expense_date' => '2026-07-01',
        'amount' => '10',
        'payee' => 'Nope',
    ])->assertForbidden();
    $this->actingAs($cashier)->get(route('expenses.show', $expense))->assertForbidden();
    $this->actingAs($cashier)->get(route('expense-categories.index'))->assertForbidden();
});

it('allows inventory clerks to log expenses', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner(['currency' => 'KES']);
    $clerk = $this->addMember($business, BusinessRole::InventoryClerk, branchIds: [$branch->id]);
    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);

    $this->actingAs($clerk)
        ->post(route('expenses.store'), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '2026-07-01',
            'amount' => '50.00',
            'payee' => 'Boda transport',
            'description' => 'Delivery transport',
        ])
        ->assertRedirect();

    expect(Expense::query()->forBusiness($business)->where('payee', 'Boda transport')->exists())->toBeTrue();
});

it('allows cashiers to log expenses when owner enables the setting', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner(['currency' => 'KES']);
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)
        ->patch(route('staff.settings.update'), [
            'cashiers_can_log_expenses' => true,
        ])
        ->assertRedirect();

    expect($business->fresh()->cashiers_can_log_expenses)->toBeTrue();

    $this->actingAs($cashier)
        ->post(route('expenses.store'), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '2026-07-01',
            'amount' => '20.00',
            'payee' => 'Airtime',
            'description' => 'Store airtime',
        ])
        ->assertRedirect();

    expect(Expense::query()->forBusiness($business)->where('payee', 'Airtime')->exists())->toBeTrue();
});

it('isolates expenses and attachments across tenants', function () {
    ['owner' => $ownerA] = $this->createBusinessWithOwner(['name' => 'Tenant A']);
    ['owner' => $ownerB, 'business' => $businessB, 'branch' => $branchB] = $this->createBusinessWithOwner([
        'name' => 'Tenant B',
        'currency' => 'KES',
    ]);

    $categoryB = ExpenseCategory::factory()->create(['business_id' => $businessB->id]);
    $expenseB = Expense::factory()->create([
        'business_id' => $businessB->id,
        'branch_id' => $branchB->id,
        'expense_category_id' => $categoryB->id,
        'created_by' => $ownerB->id,
        'currency' => 'KES',
        'payee' => 'Secret Vendor',
    ]);

    Storage::disk('attachments')->put('secret.pdf', 'secret-bytes');
    $attachmentB = Attachment::factory()->create([
        'business_id' => $businessB->id,
        'attachable_type' => $expenseB->getMorphClass(),
        'attachable_id' => $expenseB->id,
        'disk' => 'attachments',
        'path' => 'secret.pdf',
        'uploaded_by' => $ownerB->id,
    ]);

    $this->actingAs($ownerA)
        ->get(route('expenses.show', $expenseB))
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->post(route('expenses.update', $expenseB), [
            'branch_id' => $branchB->id,
            'expense_category_id' => $categoryB->id,
            'expense_date' => '2026-07-01',
            'amount' => '1',
            'payee' => 'Hijack',
        ])
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->get(route('attachments.download', $attachmentB))
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('expenses.data', 0));
});

it('validates expense fields and receipt uploads', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);
    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)
        ->from(route('expenses.create'))
        ->post(route('expenses.store'), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '',
            'amount' => '0',
            'payee' => '',
            'receipt' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors(['expense_date', 'amount', 'payee', 'receipt']);

    $otherBranch = Branch::factory()->create();
    $this->actingAs($owner)
        ->post(route('expenses.store'), [
            'branch_id' => $otherBranch->id,
            'expense_category_id' => $category->id,
            'expense_date' => '2026-07-01',
            'amount' => '100',
            'payee' => 'Vendor',
        ])
        ->assertForbidden();
});

it('serves receipts only to authorized expense managers', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);
    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);
    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);
    $expense = Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'created_by' => $owner->id,
        'currency' => 'KES',
    ]);

    Storage::disk('attachments')->put('receipts/bill.pdf', 'pdf-bytes');
    $attachment = Attachment::factory()->create([
        'business_id' => $business->id,
        'attachable_type' => $expense->getMorphClass(),
        'attachable_id' => $expense->id,
        'disk' => 'attachments',
        'path' => 'receipts/bill.pdf',
        'original_name' => 'bill.pdf',
        'mime_type' => 'application/pdf',
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('attachments.download', $attachment))
        ->assertOk();

    $this->actingAs($cashier)
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});

it('manages expense categories with audit and blocks delete when in use', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();

    $this->actingAs($owner)
        ->post(route('expense-categories.store'), [
            'name' => 'Utilities',
            'description' => 'Power and water',
        ])
        ->assertRedirect();

    $category = ExpenseCategory::query()->forBusiness($business)->first();
    expect($category?->name)->toBe('Utilities');
    expect(AuditLog::query()->where('action', 'expense_category.created')->exists())->toBeTrue();

    Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'created_by' => $owner->id,
        'currency' => $business->currency,
    ]);

    $this->actingAs($owner)
        ->from(route('expense-categories.index'))
        ->delete(route('expense-categories.destroy', $category))
        ->assertSessionHasErrors('expense_category');
});

it('lets managers filter expenses by branch category and date', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'currency' => 'KES',
    ]);
    $manager = $this->addMember($business, BusinessRole::Manager, branchIds: [$branch->id]);
    $otherBranch = Branch::factory()->create(['business_id' => $business->id, 'name' => 'Second']);
    $rent = ExpenseCategory::factory()->create(['business_id' => $business->id, 'name' => 'Rent']);
    $fuel = ExpenseCategory::factory()->create(['business_id' => $business->id, 'name' => 'Fuel']);

    Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $rent->id,
        'created_by' => $owner->id,
        'currency' => 'KES',
        'payee' => 'Landlord',
        'expense_date' => '2026-07-01',
    ]);
    Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $otherBranch->id,
        'expense_category_id' => $fuel->id,
        'created_by' => $owner->id,
        'currency' => 'KES',
        'payee' => 'Petrol Station',
        'expense_date' => '2026-07-10',
    ]);

    $this->actingAs($manager)
        ->get(route('expenses.index', [
            'branch_id' => $branch->id,
            'expense_category_id' => $rent->id,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-05',
            'search' => 'Landlord',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.payee', 'Landlord'));
});
