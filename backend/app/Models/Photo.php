<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'listing_id', 'status', 'original_path', 'mime_type', 'size_bytes', 'width', 'height',
    'variants', 'alt_text', 'position', 'failure_reason',
])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'pending',
        'variants' => '{}',
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => PhotoStatus::class,
            'variants' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @param  Builder<Photo>  $query */
    public function scopeReady(Builder $query): void
    {
        $query->where('status', PhotoStatus::Ready);
    }

    /**
     * URL publique d'une déclinaison (null tant que la photo n'est pas prête).
     */
    public function variantUrl(string $size, string $format = 'webp'): ?string
    {
        $path = $this->variants[$size][$format] ?? null;

        return $path ? Storage::disk(config('photos.public_disk'))->url($path) : null;
    }

    /** Chemins publics de toutes les déclinaisons (pour suppression). */
    public function variantPaths(): array
    {
        $paths = [];

        foreach ($this->variants ?? [] as $variant) {
            foreach (['webp', 'avif'] as $format) {
                if (! empty($variant[$format])) {
                    $paths[] = $variant[$format];
                }
            }
        }

        return $paths;
    }
}
