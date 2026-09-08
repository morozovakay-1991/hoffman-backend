<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class DiaryDay extends Model
{
    /** @use HasFactory<\Database\Factories\DiaryDayFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'day_number',
        'title',
        'task_text',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var list<string>
     */
    public $translatable = [
        'title',
        'task_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
        ];
    }

    /**
     * @return HasMany<DiaryEntry, $this>
     */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }
}
