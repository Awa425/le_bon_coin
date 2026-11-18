<?php

namespace App\Modules\Media\Http\Resources;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Photo */
class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ready = $this->status === PhotoStatus::Ready;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'position' => $this->position,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text,
            'failure_reason' => $this->failure_reason,
            // { thumb: { width, height, webp, avif }, card: …, detail: …, full: … } — URLs publiques (CDN).
            'variants' => $ready
                ? collect($this->variants)->map(fn (array $variant, string $name) => [
                    'width' => $variant['width'],
                    'height' => $variant['height'] ?? null,
                    'webp' => $this->variantUrl($name, 'webp'),
                    'avif' => $this->variantUrl($name, 'avif'),
                ])->all()
                : (object) [],
        ];
    }
}
