<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_the_authenticated_users_own_invoices(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $ownInvoices = Invoice::factory()->count(3)->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        $otherUser = User::factory()->create();
        Invoice::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/billing/invoices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values();
        $this->assertSame($ownInvoices->pluck('id')->sort()->values()->all(), $returnedIds->all());
    }

    public function test_it_paginates_the_invoice_list(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(20)->create(['user_id' => $user->id]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/billing/invoices?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->getJson('/api/v1/billing/invoices')->assertStatus(401);
    }

    public function test_policy_blocks_access_to_another_users_invoice_by_substituted_id(): void
    {
        $owner = User::factory()->create();
        $foreignInvoice = Invoice::factory()->create(['user_id' => $owner->id]);

        $attacker = User::factory()->create();

        $response = $this->actingAsApiUser($attacker)->getJson("/api/v1/billing/invoices/{$foreignInvoice->id}");

        $response->assertStatus(403);
    }

    public function test_a_user_can_view_their_own_invoice_by_id(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAsApiUser($user)->getJson("/api/v1/billing/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }
}
