<?php

namespace App\Http\Controllers\Settings\Concerns;

use App\Models\Business;
use App\Support\Deployment;
use App\Support\Tenancy\TenantContext;

trait AuthorizesDesktopSettings
{
    protected function desktopBusiness(TenantContext $tenant): Business
    {
        abort_unless(Deployment::isDesktop(), 404);

        $business = $tenant->business();
        abort_unless($business, 403);

        $this->authorize('update', $business);

        return $business;
    }
}
