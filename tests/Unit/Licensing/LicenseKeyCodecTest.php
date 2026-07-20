<?php

use App\Support\Licensing\LicenseKeyCodec;

it('round-trips signed online license keys', function () {
    config(['deployment.license.secret' => 'unit-test-secret']);

    $codec = new LicenseKeyCodec;
    $key = $codec->issue([
        'edition' => 'pro',
        'expires_at' => '2027-12-31',
    ]);

    $claims = $codec->decode($key);

    expect($key)->toStartWith('KOS1.')
        ->and($claims['edition']->value)->toBe('pro')
        ->and($claims['expires_at']?->toDateString())->toBe('2027-12-31')
        ->and($claims['machine_id'])->toBeNull();
});

it('rejects tampered license keys', function () {
    config(['deployment.license.secret' => 'unit-test-secret']);

    $codec = new LicenseKeyCodec;
    $key = $codec->issue([
        'edition' => 'starter',
        'expires_at' => '2027-01-01',
    ]);

    expect(fn () => $codec->decode($key.'x'))
        ->toThrow(InvalidArgumentException::class);
});
