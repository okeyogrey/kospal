<?php

namespace App\Providers;

use App\Enums\ReportType;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StaffShift;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Policies\ReportPolicy;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureLocalePersistence();
        $this->configureRouteBindings();
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(ReportType::class, ReportPolicy::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('invitations', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(12)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('attachments', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }

    protected function configureLocalePersistence(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $locale = $event->user->preferred_locale;
            $locales = array_keys(config('kospal.locales', []));

            if (is_string($locale) && in_array($locale, $locales, true)) {
                session()->put('locale', $locale);
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRouteBindings(): void
    {
        Route::bind('branch', function (string $value): Branch {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Branch::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('membership', function (string $value): BusinessMembership {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return BusinessMembership::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('invitation', function (string $value): Invitation {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Invitation::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('category', function (string $value): Category {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Category::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('supplier', function (string $value): Supplier {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Supplier::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('product', function (string $value): Product {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Product::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('stockTransfer', function (string $value): StockTransfer {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return StockTransfer::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('sale', function (string $value): Sale {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Sale::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('shift', function (string $value): StaffShift {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return StaffShift::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('customer', function (string $value): Customer {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Customer::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('expenseCategory', function (string $value): ExpenseCategory {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return ExpenseCategory::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('expense', function (string $value): Expense {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Expense::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('attachment', function (string $value): Attachment {
            $businessId = app(TenantContext::class)->businessId();
            abort_unless($businessId, 404);

            return Attachment::query()
                ->forBusiness($businessId)
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
