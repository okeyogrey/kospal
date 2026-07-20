<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinesses;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesBusinesses;
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_a_business_are_redirected_to_onboarding()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertRedirect(route('onboarding.create'));
    }

    public function test_platform_super_admins_are_redirected_to_platform_tools_in_web_mode()
    {
        config(['deployment.mode' => 'web']);

        $admin = User::factory()->platformSuperAdmin()->create();
        $this->actingAs($admin);

        $this->get(route('dashboard'))
            ->assertRedirect(route('platform.subscription-requests.index'));
    }

    public function test_platform_super_admins_onboard_locally_in_desktop_mode()
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $this->actingAs($admin);

        $this->get(route('dashboard'))
            ->assertRedirect(route('onboarding.create'));
    }

    public function test_authenticated_business_members_can_visit_the_dashboard()
    {
        ['owner' => $user] = $this->createBusinessWithOwner();
        $this->actingAs($user);
        $this->withoutVite();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('metrics')
                ->has('recent_sales')
                ->has('top_products')
                ->has('filters')
            );
    }
}
