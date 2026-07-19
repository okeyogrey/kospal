<?php

use App\Enums\BusinessRole;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('attachments');
});

it('allows expense managers to download receipts and blocks cashiers', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();

    $category = ExpenseCategory::factory()->create(['business_id' => $business->id]);
    $expense = Expense::factory()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'created_by' => $owner->id,
    ]);

    $attachment = Attachment::factory()->create([
        'business_id' => $business->id,
        'attachable_type' => $expense->getMorphClass(),
        'attachable_id' => $expense->id,
        'disk' => 'attachments',
        'path' => 'receipts/demo.pdf',
        'original_name' => 'demo.pdf',
        'mime_type' => 'application/pdf',
        'uploaded_by' => $owner->id,
    ]);
    Storage::disk('attachments')->put($attachment->path, 'pdf-bytes');

    $cashier = $this->addMember($business, BusinessRole::Cashier, branchIds: [$branch->id]);

    $this->actingAs($owner)
        ->get(route('attachments.download', $attachment))
        ->assertOk();

    $this->actingAs($cashier)
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});

it('rejects disallowed receipt mime types when recording expenses', function () {
    ['owner' => $owner, 'business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner();
    $category = ExpenseCategory::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('expenses.store'), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '10.00',
            'payee' => 'Vendor',
            'receipt' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])
        ->assertSessionHasErrors('receipt');
});

it('registers rate limiters for sensitive actions', function () {
    expect(RateLimiter::limiter('sensitive'))->not->toBeNull()
        ->and(RateLimiter::limiter('invitations'))->not->toBeNull()
        ->and(RateLimiter::limiter('exports'))->not->toBeNull()
        ->and(RateLimiter::limiter('attachments'))->not->toBeNull()
        ->and(RateLimiter::limiter('login'))->not->toBeNull();

    $request = request();
    $request->setUserResolver(fn () => null);

    $limit = RateLimiter::limiter('sensitive')($request);
    expect($limit)->toBeInstanceOf(Limit::class);
});
