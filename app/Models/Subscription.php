<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * Statuses under which a subscription still grants access to paid
     * content: a currently paid period ("active"), a trial period
     * ("trialing"), or a billing retry window after a failed renewal
     * ("in_grace_period"), during which access is not yet revoked.
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['active', 'trialing', 'in_grace_period'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'payment_provider',
        'product_id',
        'transaction_id',
        'original_transaction_id',
        'external_customer_id',
        'external_subscription_id',
        'status',
        'auto_renew',
        'currency',
        'country',
        'starts_at',
        'trial_ends_at',
        'expires_at',
        'cancelled_at',
        'cancel_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The single source of truth for whether a subscription currently
     * grants access: its status must be one of self::ACTIVE_STATUSES,
     * and, if it has an expiry, that expiry must not have passed yet.
     */
    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Query-level equivalent of isActive(), for filtering subscriptions
     * in the database rather than in PHP.
     *
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
