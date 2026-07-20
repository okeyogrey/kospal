<?php

namespace App\Support\Plans;

use App\Services\Deployment\FeatureFlags\PlanBasedFeatureFlagService;

/**
 * @deprecated Inject App\Contracts\FeatureFlagService instead.
 *
 * Kept as a named alias so existing type-hints and tests keep resolving
 * to the plan-based feature flag implementation.
 */
class PlanLimitChecker extends PlanBasedFeatureFlagService {}
