<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class LegalDocument extends Model
{
    use HasTranslations;

    public const SLUG_PRIVACY = 'privacy';

    public const SLUG_TERMS = 'terms';

    public const SLUG_LICENSE = 'license';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'body',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var list<string>
     */
    public $translatable = [
        'title',
        'body',
    ];
}
