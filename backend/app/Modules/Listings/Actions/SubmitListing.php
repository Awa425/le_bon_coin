<?php

namespace App\Modules\Listings\Actions;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Modules\Catalog\Services\CategorySchema;
use App\Modules\Catalog\Validation\AttributeRules;
use App\Modules\Listings\ListingFieldNames;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Soumission d'un brouillon à la modération : contrôle complet selon le schéma
 * de la catégorie (champs obligatoires inclus), puis passage en EN_MODERATION.
 */
class SubmitListing
{
    public function __construct(
        private readonly CategorySchema $schema,
        private readonly AttributeRules $attributeRules,
    ) {}

    public function __invoke(Listing $listing): Listing
    {
        $target = ListingStatus::PendingModeration;

        if (! $listing->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Une annonce au statut « {$listing->status->label()} » ne peut pas être soumise.",
            ]);
        }

        $category = $listing->category;
        $schema = $this->schema->for($category);
        $priced = in_array($listing->type, [ListingType::Sale, ListingType::Rental], true);

        Validator::make(
            [
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price,
                'location' => $listing->location?->jsonSerialize(),
                'category_id' => $category->isLeaf() ? $category->id : null,
                AttributeRules::FIELD => $listing->attribute_values ?: null,
                'photos' => $listing->photos()->ready()->count(),
            ],
            [
                'title' => ['required', 'string', 'min:3', 'max:100'],
                'description' => ['required', 'string', 'min:10', 'max:4000'],
                'price' => [$priced ? 'required' : 'nullable', 'numeric', 'min:0'],
                'location' => ['required', 'array'],
                'category_id' => ['required'],
                // 1 à 30 photos par annonce (cahier des charges §4) ; facultatif pour recherche / service / emploi.
                'photos' => ['integer', 'min:'.($priced ? 1 : 0)],
                ...$this->attributeRules->rules($schema, enforceRequired: true),
            ],
            [
                'category_id.required' => 'Choisissez une sous-catégorie : une annonce doit être déposée dans une catégorie finale.',
                'photos.min' => 'Ajoutez au moins une photo.',
            ],
            [...ListingFieldNames::all(), ...$this->attributeRules->attributeNames($schema)],
        )->validate();

        $listing->status = $target;
        $listing->save();

        return $listing;
    }
}
