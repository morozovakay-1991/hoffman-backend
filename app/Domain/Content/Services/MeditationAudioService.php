<?php

namespace App\Domain\Content\Services;

use App\Models\Meditation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Issues temporary, presigned URLs to meditation audio files stored on S3.
 */
class MeditationAudioService
{
    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function presignedUrl(Meditation $meditation): array
    {
        $expiresAt = now()->addHour();

        return [
            'url' => Storage::disk('s3')->temporaryUrl($meditation->audio_path, $expiresAt),
            'expires_at' => $expiresAt,
        ];
    }
}
