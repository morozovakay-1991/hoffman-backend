<?php

namespace App\Models;

use App\Models\Concerns\HasExclusiveFeatured;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Meditation extends Model
{
    /** @use HasFactory<\Database\Factories\MeditationFactory> */
    use HasFactory;

    use HasExclusiveFeatured;

    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'short_description',
        'full_description',
        'cover_image_path',
        'audio_path',
        'duration_seconds',
        'is_free',
        'is_published',
        'is_featured',
        'sort_order',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var list<string>
     */
    public $translatable = [
        'title',
        'short_description',
        'full_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'is_free' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'topic_meditation');
    }
}
