<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locales = array_keys(config('kospal.locales', []));

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $locales)],
        ]);

        $locale = $validated['locale'];

        $request->session()->put('locale', $locale);

        $user = $request->user();

        if ($user !== null && $user->preferred_locale !== $locale) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        $business = $user?->currentBusiness;

        if (
            $business !== null
            && $request->boolean('persist_business')
            && $business->owner_user_id === $user->id
            && $business->default_locale !== $locale
        ) {
            $business->forceFill(['default_locale' => $locale])->save();
        }

        return back();
    }
}
