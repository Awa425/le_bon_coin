<?php

namespace App\Modules\Listings\Actions;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Modules\Catalog\Services\CategorySchema;
use App\Modules\Catalog\Validation\AttributeRules;
use App\Support\Geo\GeoPoint;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SaveListingDraft
{
    public function __construct(
        private readonly CategorySchema $schema,
        private readonly AttributeRules $attributeRules,
    ) {}

    /** @param  array<string, mixed>  $data  données déjà validées par ListingDraftRequest */
    public function create(User $seller, array $data): Listing
    {
        $listing = new Listing(['status' => ListingStatus::Draft]);
        $listing->user()->associate($seller);

        return $this->fill($listing, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Listing $listing, array $data): Listing
    {
        return $this->fill($listing, $data);
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Listing $listing, array $data): Listing
    {
        $listing->fill(Arr::only($data, ['category_id', 'type', 'title', 'description', 'price', 'hide_phone']));

        if (array_key_exists('location', $data)) {
            $listing->location = $data['location'] === null
                ? null
                : new GeoPoint((float) $data['location']['lat'], (float) $data['location']['lng']);
        }

        $category = Category::query()->findOrFail($listing->category_id);
        $schema = $this->schema->for($category);

        if (array_key_exists(AttributeRules::FIELD, $data)) {
            $listing->attribute_values = $this->attributeRules->normalize($schema, $data[AttributeRules::FIELD] ?? []);
        } elseif ($listing->isDirty('category_id')) {
            // Changement de catégorie : on ne conserve que les attributs encore présents dans le nouveau schéma.
            $listing->attribute_values = Arr::only($listing->attribute_values ?? [], $schema->keys()->all());
        }

        if (! $listing->exists || ($listing->isDirty('title') && $listing->status === ListingStatus::Draft)) {
            $listing->slug = Str::limit(Str::slug($listing->title), 80, '').'-'.Str::lower(Str::random(8));
        }

        $listing->save();

        return $listing;
    }
}
