<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\VerificationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationRequest extends Model
{
    /** @use HasFactory<VerificationRequestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reviewer_id',
        'status',
        'last_name',
        'first_name',
        'phone',
        'graduate_directory_id',
        'duplicate_of_verification_request_id',
        'institution_name',
        'graduation_year',
        'document_path',
        'rejection_reason',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'graduation_year' => 'integer',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<GraduateDirectory, $this>
     */
    public function graduateDirectoryEntry(): BelongsTo
    {
        return $this->belongsTo(GraduateDirectory::class, 'graduate_directory_id');
    }

    /**
     * The other user's already-confirmed verification request for the same
     * graduate directory entry, when this request was flagged as a duplicate.
     *
     * @return BelongsTo<VerificationRequest, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_verification_request_id');
    }
}
