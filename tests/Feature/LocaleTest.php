<?php

use App\Support\Time\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('updates the session locale for supported languages', function (string $locale) {
    ['owner' => $user] = $this->createBusinessWithOwner();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), ['locale' => $locale])
        ->assertRedirect(route('dashboard'));

    expect(session('locale'))->toBe($locale)
        ->and($user->fresh()->preferred_locale)->toBe($locale);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', $locale));
})->with(['en', 'fr', 'rn']);

it('rejects unsupported locales', function () {
    ['owner' => $user] = $this->createBusinessWithOwner();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale');
});

it('persists business default locale when the owner requests it', function () {
    ['owner' => $user, 'business' => $business] = $this->createBusinessWithOwner([
        'default_locale' => 'en',
    ]);

    $this->actingAs($user)
        ->post(route('locale.update'), [
            'locale' => 'fr',
            'persist_business' => true,
        ])
        ->assertRedirect();

    expect($user->fresh()->preferred_locale)->toBe('fr')
        ->and($business->fresh()->default_locale)->toBe('fr');
});

it('applies preferred locale after login when session has no locale', function () {
    ['owner' => $user] = $this->createBusinessWithOwner();
    $user->forceFill(['preferred_locale' => 'rn'])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect(session('locale'))->toBe('rn');
});

it('falls back to business default locale when session and user preferences are empty', function () {
    ['owner' => $user, 'business' => $business] = $this->createBusinessWithOwner([
        'default_locale' => 'fr',
    ]);
    $user->forceFill(['preferred_locale' => null])->save();
    $business->forceFill(['default_locale' => 'fr'])->save();

    $this->actingAs($user)
        ->withSession([])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'fr'));
});

it('keeps French and Kirundi translation keys aligned with English', function () {
    $en = require lang_path('en/kospal.php');
    $fr = require lang_path('fr/kospal.php');
    $rn = require lang_path('rn/kospal.php');

    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = [...$keys, ...$flatten($value, $path)];
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    };

    $enKeys = $flatten($en);
    expect($flatten($fr))->toEqual($enKeys)
        ->and($flatten($rn))->toEqual($enKeys);

    foreach ($flatten($fr) as $key) {
        $value = data_get($fr, $key);
        expect($value)->not->toStartWith('[FR]');
    }

    foreach (['nav.dashboard', 'topbar.language', 'states.empty.title', 'welcome.cta_login'] as $key) {
        expect(data_get($rn, $key))->not->toStartWith('[RN]')
            ->and(data_get($rn, $key))->not->toBeEmpty();
    }
});

it('defaults Burundi onboarding locale and timezone', function () {
    expect(BusinessClock::localeForCountry('BI'))->toBe('fr')
        ->and(BusinessClock::resolve(null, 'BI'))->toBe('Africa/Bujumbura')
        ->and(BusinessClock::resolve(null, 'KE'))->toBe('Africa/Nairobi');
});
