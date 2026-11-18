<?php

namespace App\Modules\Media\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Modules\Media\Images\ImageProcessor;
use App\Modules\Media\Images\InvalidImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Traitement asynchrone d'une photo envoyée : génération des déclinaisons publiques,
 * puis suppression de l'original (qui peut contenir des métadonnées EXIF / GPS).
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly Photo $photo) {}

    public function handle(): void
    {
        $photo = $this->photo->fresh();

        if ($photo === null || $photo->status !== PhotoStatus::Processing || $photo->original_path === null) {
            return;
        }

        $uploads = Storage::disk(config('photos.uploads_disk'));
        $public = Storage::disk(config('photos.public_disk'));
        $originalPath = $photo->original_path;

        try {
            $processed = ImageProcessor::fromConfig()->process($uploads->get($originalPath));
        } catch (InvalidImage $e) {
            $this->markFailed($photo, $e->getMessage());

            return;
        }

        $base = "variants/{$photo->listing_id}/{$photo->id}";
        $variants = [];

        foreach ($processed->variants as $name => $variant) {
            $paths = ['webp' => "{$base}/{$name}.webp", 'avif' => "{$base}/{$name}.avif"];
            $public->put($paths['webp'], $variant->webp, $this->objectOptions('image/webp'));
            $public->put($paths['avif'], $variant->avif, $this->objectOptions('image/avif'));
            $variants[$name] = ['width' => $variant->width, 'height' => $variant->height, ...$paths];
        }

        $photo->update([
            'status' => PhotoStatus::Ready,
            'mime_type' => $processed->mimeType,
            'width' => $processed->width,
            'height' => $processed->height,
            'variants' => $variants,
            'failure_reason' => null,
            'original_path' => null,
        ]);

        $uploads->delete($originalPath);
    }

    /** Échec définitif après les nouvelles tentatives (stockage indisponible, etc.). */
    public function failed(?Throwable $exception): void
    {
        $photo = $this->photo->fresh();

        if ($photo !== null) {
            Log::warning('Traitement photo en échec', ['photo' => $photo->id, 'error' => $exception?->getMessage()]);
            $this->markFailed($photo, 'Le traitement de la photo a échoué. Réessayez.');
        }
    }

    private function markFailed(Photo $photo, string $reason): void
    {
        $original = $photo->original_path;

        $photo->update(['status' => PhotoStatus::Failed, 'failure_reason' => $reason, 'original_path' => null]);

        if ($original !== null) {
            Storage::disk(config('photos.uploads_disk'))->delete($original);
        }
    }

    /** Déclinaisons immuables (nom unique par photo) : cache CDN / navigateur d'un an. */
    private function objectOptions(string $contentType): array
    {
        return ['ContentType' => $contentType, 'CacheControl' => 'public, max-age=31536000, immutable'];
    }
}
