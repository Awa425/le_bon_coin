<?php

namespace App\Modules\Listings\Http\Resources;

use App\Enums\PhotoStatus;
use App\Models\Listing;
use App\Modules\Media\Http\Resources\PhotoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'attribute_values' => (object) ($this->attribute_values ?? []),
            // Position exacte réservée au vendeur ; la fiche publique affichera une zone approximative (ANN-03).
            'location' => $this->when($isOwner, fn () => $this->location),
            'hide_phone' => $this->hide_phone,
            // Le vendeur voit toutes ses photos (en traitement, en échec) ; le public, seulement les photos prêtes.
            'photos' => PhotoResource::collection($this->whenLoaded('photos', fn () => $isOwner
                ? $this->photos
                : $this->photos->where('status', PhotoStatus::Ready)->values())),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
