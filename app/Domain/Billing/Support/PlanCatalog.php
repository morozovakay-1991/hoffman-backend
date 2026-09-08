<?php

namespace App\Domain\Billing\Support;

class PlanCatalog
{
    /**
     * @return list<array{id: string, name: string, interval: ?string, prices: array<string, int>}>
     */
    public static function all(): array
    {
        return config('billing.plans', []);
    }

    /**
     * @return array{id: string, name: string, interval: ?string, prices: array<string, int>}|null
     */
    public static function find(string $planId): ?array
    {
        foreach (self::all() as $plan) {
            if ($plan['id'] === $planId) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * The plan's price in the minor unit of the given currency (cents / kopecks).
     */
    public static function priceFor(string $planId, string $currency): ?int
    {
        return self::find($planId)['prices'][strtoupper($currency)] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }
}
