<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\BusinessOnboardingService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for local development only.
     */
    public function run(): void
    {
        PlatformSetting::setValue('support_email', ['value' => 'support@kospal.test']);
        PlatformSetting::setValue('default_trial_days', ['value' => 14]);
        PlatformSetting::setPaymentInstructions([
            'title' => 'How to pay for KOSPAL',
            'body' => "Transfer the plan fee using the bank or mobile money details below.\nKeep your receipt or confirmation code, then submit it on the Subscription page for manual approval.",
            'bank_name' => 'Equity Bank Kenya',
            'account_name' => 'KOSPAL Limited',
            'account_number' => '0123456789',
            'mobile_money' => 'M-Pesa Paybill 123456 / Account: KOSPAL',
            'support_note' => 'Questions? Email support@kospal.test with your business name and transaction code.',
        ]);

        User::factory()->platformSuperAdmin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@kospal.test',
        ]);

        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@kospal.test',
        ]);

        $result = app(BusinessOnboardingService::class)->onboard($owner, [
            'name' => 'Demo Market Nairobi',
            'country' => 'KE',
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'branch_name' => 'Westlands Branch',
            'branch_city' => 'Nairobi',
            'branch_address' => 'Westlands Road',
            'branch_phone' => '+254700000001',
        ]);

        /** @var Business $business */
        $business = $result['business'];
        $business->update([
            'plan' => Plan::Pro,
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_ends_at' => now()->addYear(),
        ]);

        $secondBranch = Branch::factory()->create([
            'business_id' => $business->id,
            'name' => 'Karen Branch',
            'city' => 'Nairobi',
        ]);

        $manager = User::factory()->create([
            'name' => 'Demo Manager',
            'email' => 'manager@kospal.test',
            'current_business_id' => $business->id,
            'current_branch_id' => $result['branch']->id,
        ]);

        BusinessMembership::factory()->manager()->create([
            'business_id' => $business->id,
            'user_id' => $manager->id,
        ]);

        $cashier = User::factory()->create([
            'name' => 'Demo Cashier',
            'email' => 'cashier@kospal.test',
            'current_business_id' => $business->id,
            'current_branch_id' => $result['branch']->id,
        ]);

        BusinessMembership::factory()->cashier()->create([
            'business_id' => $business->id,
            'user_id' => $cashier->id,
        ]);

        $cashier->branches()->attach($result['branch']->id, [
            'business_id' => $business->id,
        ]);

        $clerk = User::factory()->create([
            'name' => 'Demo Inventory Clerk',
            'email' => 'clerk@kospal.test',
            'current_business_id' => $business->id,
            'current_branch_id' => $secondBranch->id,
        ]);

        BusinessMembership::factory()->inventoryClerk()->create([
            'business_id' => $business->id,
            'user_id' => $clerk->id,
        ]);

        $clerk->branches()->attach($secondBranch->id, [
            'business_id' => $business->id,
        ]);

        $otherOwner = User::factory()->create([
            'name' => 'Other Owner',
            'email' => 'other@kospal.test',
        ]);

        app(BusinessOnboardingService::class)->onboard($otherOwner, [
            'name' => 'Bujumbura Shop',
            'country' => 'BI',
            'currency' => 'BIF',
            'timezone' => 'Africa/Bujumbura',
            'branch_name' => 'Centre Ville',
            'branch_city' => 'Bujumbura',
        ]);
    }
}
