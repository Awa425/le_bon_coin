<?php

namespace App\Models;

use App\Casts\GeoPointCast;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'category_id', 'type', 'title', 'slug', 'description', 'price', 'currency',
    'status', 'attribute_values', 'location', 'hide_phone', 'published_at', 'expires_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUuids;

    /** Durée de vie d'une annonce publiée (TDB-01). */
    public const LIFETIME_DAYS = 60;

    /** Valeurs par défaut alignées sur celles de la migration. */
    protected $attributes = [
        'type' => 'sale',
        'status' => 'draft',
        'currency' => 'EUR',
        'attribute_values' => '{}',
        'hide_phone' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'status' => ListingStatus::class,
            'price' => 'decimal:2',
            'attribute_values' => 'array',
            'location' => GeoPointCast::class,
            'hide_phone' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** Le vendeur ne peut modifier que ses brouillons et ses annonces refusées. */
    public function isEditable(): bool
    {
        return in_array($this->status, [ListingStatus::Draft, ListingStatus::Refused], true);
    }
}
