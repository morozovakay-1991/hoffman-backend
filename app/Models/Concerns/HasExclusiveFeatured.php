<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Keeps at most one is_featured=true record per content type. Enforced on the
 * model's `saved` event rather than in the Filament form, so it holds however
 * the record is saved (admin panel, tinker, seeders, future API endpoints):
 * featuring one record un-features every other record of the same model.
 */
trait HasExclusiveFeatured
{
    public static function bootHasExclusiveFeatured(): void
    {
        static::saved(function (Model $model) {
            if (! $model->getAttribute('is_featured')) {
                return;
            }

            // A query-builder update fires no model events, so this can't recurse.
            static::query()
                ->whereKeyNot($model->getKey())
                ->where('is_featured', true)
                ->update(['is_featured' => false]);
        });
    }
}
