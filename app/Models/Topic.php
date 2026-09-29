<?php

namespace App\Models;

use App\Models\Concerns\HasExclusiveFeatured;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Topic extends Model
{
    /** @use HasFactory<\Database\Factories\TopicFactory> */
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
        'subtitle',
        'full_description',
        'cover_image_path',
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
        'subtitle',
        'full_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'topic_tool');
    }

    /**
     * @return BelongsToMany<Meditation, $this>
     */
    public function meditations(): BelongsToMany
    {
        return $this->belongsToMany(Meditation::class, 'topic_meditation');
    }
}
