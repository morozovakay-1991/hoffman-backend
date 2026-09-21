<?php

namespace App\Models;

use App\Models\Concerns\HasOtpCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 */
class EmailChangeRequest extends Model
{
    use HasOtpCode;

    public const MAX_ATTEMPTS = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'old_email',
        'new_email',
        'code',
        'attempts',
        'expires_at',
        'confirmed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
