<?php

namespace App\Domain\Content\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Builds permanent public URLs to content cover images stored on S3.
 *
 * Unlike meditation audio (see MeditationAudioService), covers are not
 * access-controlled: they're shown on locked cards too, so they're linked
 * directly. Public read access comes from the bucket policy on the cover
 * prefixes (articles/, tools/, topics/, meditations/covers/ — see the
 * minio-init service in docker-compose.yml), not from per-object ACLs, and
 * AWS_URL must point at the bucket's public base URL.
 */
class CoverImageService
{
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('s3')->url($path);
    }
}
