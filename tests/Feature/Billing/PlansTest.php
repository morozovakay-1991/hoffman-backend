<?php

namespace Tests\Feature\Billing;

use Tests\TestCase;

class PlansTest extends TestCase
{
    public function test_it_lists_the_available_plans_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/billing/plans');

        $response->assertOk()
            ->assertJsonCount(3, 'plans')
            ->assertJsonPath('plans.0.id', 'monthly')
            ->assertJsonStructure([
                'plans' => [
                    '*' => ['id', 'name', 'interval', 'prices'],
                ],
            ]);
    }
}
