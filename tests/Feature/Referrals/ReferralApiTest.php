<?php

use App\Models\Referral;
use App\Models\ReferralAccount;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('registers a desktop account and issues a code over the API', function () {
    $payload = $this->postJson('/api/referrals/accounts', [
        'public_uuid' => '11111111-1111-1111-1111-111111111111',
        'owner_email' => 'owner@shop.test',
        'business_name' => 'Remote Shop',
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-DDDD',
    ])->assertCreated()->json();

    expect($payload['token'])->toBeString();

    $code = $this->withToken($payload['token'])
        ->postJson('/api/referrals/codes')
        ->assertCreated()
        ->json();

    expect($code['code'])->toStartWith('KSP-');

    $this->getJson('/api/referrals/codes/'.$code['code'])
        ->assertOk()
        ->assertJsonPath('usable', true)
        ->assertJsonPath('referrer_name', 'Remote Shop');
});

it('redeems a code for a new desktop shop over the API', function () {
    $referrer = $this->postJson('/api/referrals/accounts', [
        'public_uuid' => '22222222-2222-2222-2222-222222222222',
        'owner_email' => 'a@shop.test',
        'business_name' => 'Alpha',
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-1111',
    ])->json();

    $code = $this->withToken($referrer['token'])->postJson('/api/referrals/codes')->json('code');

    $this->postJson('/api/referrals/redeem', [
        'code' => $code,
        'public_uuid' => '33333333-3333-3333-3333-333333333333',
        'owner_email' => 'b@shop.test',
        'business_name' => 'Beta',
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-2222',
    ])->assertCreated();

    expect(ReferralAccount::query()->count())->toBe(2)
        ->and(Referral::query()->count())->toBe(1);
});

it('rejects using an invite on the same machine', function () {
    $referrer = $this->postJson('/api/referrals/accounts', [
        'public_uuid' => '44444444-4444-4444-4444-444444444444',
        'owner_email' => 'same@shop.test',
        'business_name' => 'Same Box',
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-SAME',
    ])->json();

    $code = $this->withToken($referrer['token'])->postJson('/api/referrals/codes')->json('code');

    $this->postJson('/api/referrals/redeem', [
        'code' => $code,
        'public_uuid' => '55555555-5555-5555-5555-555555555555',
        'owner_email' => 'other@shop.test',
        'business_name' => 'Clone',
        'machine_id' => 'KOSPAL-AAAA-BBBB-CCCC-SAME',
    ])->assertUnprocessable();
});

it('talks to the central server from a desktop install', function () {
    config([
        'deployment.mode' => 'desktop',
        'kospal.referral.server_url' => 'https://kospal.test',
        'deployment.license.machine_id_path' => storage_path('framework/testing/machine_id_'.uniqid('', true)),
    ]);

    Http::fake([
        'https://kospal.test/api/referrals/accounts' => Http::response([
            'token' => 'desktop-token',
            'public_uuid' => '66666666-6666-6666-6666-666666666666',
        ], 201),
        'https://kospal.test/api/referrals/codes' => Http::response([
            'code' => 'KSP-REMOTE',
            'expires_at' => now()->addDays(3)->toIso8601String(),
            'url' => 'https://kospal.test/r/KSP-REMOTE',
        ], 201),
    ]);

    ['owner' => $owner, 'business' => $business] = $this->createBusinessWithOwner();

    $code = app(ReferralService::class)->issueCode($business, $owner);

    expect($code->code)->toBe('KSP-REMOTE')
        ->and($business->fresh()->referralAccount?->remote_token)->toBe('desktop-token');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/referrals/accounts'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/referrals/codes'));
});
