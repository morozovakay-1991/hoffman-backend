<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\Resources\SubscriptionResource\Pages\ViewSubscription;
use App\Filament\Resources\SubscriptionResource\RelationManagers\InvoicesRelationManager;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/subscriptions')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/subscriptions')->assertForbidden();
    }

    public function test_an_admin_can_see_and_filter_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $activeStripe = Subscription::factory()->create([
            'status' => 'active',
            'payment_provider' => 'stripe',
        ]);
        $cancelledCloudPayments = Subscription::factory()->cancelled()->create([
            'payment_provider' => 'cloudpayments',
        ]);

        $this->actingAs($admin)->get('/backend/subscriptions')->assertSuccessful();

        Livewire::test(ListSubscriptions::class)
            ->assertCanSeeTableRecords([$activeStripe, $cancelledCloudPayments])
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$activeStripe])
            ->assertCanNotSeeTableRecords([$cancelledCloudPayments])
            ->resetTableFilters()
            ->filterTable('payment_provider', 'cloudpayments')
            ->assertCanSeeTableRecords([$cancelledCloudPayments])
            ->assertCanNotSeeTableRecords([$activeStripe]);
    }

    public function test_an_admin_can_view_a_subscription_with_its_invoices(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
        ]);

        $this->actingAs($admin)->get("/backend/subscriptions/{$subscription->id}")->assertSuccessful();

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $subscription,
            'pageClass' => ViewSubscription::class,
        ])->assertCanSeeTableRecords([$invoice]);
    }
}
