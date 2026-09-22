<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case InGracePeriod = 'in_grace_period';
    case OnHold = 'on_hold';
    case Paused = 'paused';
    case Pending = 'pending';

    /**
     * Not a persisted status: BillingService::latestSubscriptionFor() uses
     * it as an unsaved placeholder when a user has never subscribed.
     */
    case None = 'none';
}
