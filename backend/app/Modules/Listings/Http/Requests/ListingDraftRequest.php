<?php

namespace App\Modules\Listings\Http\Requests;

use App\Enums\ListingType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Listing;
use App\Modules\Catalog\Services\CategorySchema;
use App\Modules\Catalog\Validation\AttributeRules;
use App\Modules\Listings\ListingFieldNames;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Création / sauvegarde automatique d'un brouillon (tunnel de dépôt en 5 étapes).
 * Les champs obligatoires ne sont exigés qu'à la soumission ; ici on contrôle les types,
 * les bornes et la conformité au schéma d'attributs de la catégorie (DEP-01).
 */
class ListingDraftRequest extends FormRequest
{
    private ?Category $resolvedCategory = null;

    /** Contrôle d'accès avant toute validation : un tiers reçoit 403, jamais le détail des erreurs. */
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing === null || $this->user()->can('update', $listing);
    }

    public function rules(): array
    {
        $creating = $this->route('listing') === null;

        $rules = [
            'category_id' => [
                $creating ? 'required' : 'sometimes',
                'uuid',
                Rule::exists('categories', 'id'),
                $this->leafCategoryRule(),
            ],
            'type' => ['sometimes', Rule::enum(ListingType::class)],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:3', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'location' => ['sometimes', 'nullable', 'array:lat,lng'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'hide_phone' => ['sometimes', 'boolean'],
        ];

        $schema = $this->schema();

        if ($schema !== null) {
            $rules = [...$rules, ...app(AttributeRules::class)->rules($schema, enforceRequired: false)];
            // En modification, les attributs ne sont revalidés que s'ils sont envoyés.
            array_unshift($rules[AttributeRules::FIELD], 'sometimes');
        }

        return $rules;
    }

    public function attributes(): array
    {
        $schema = $this->schema();

        return [
            ...ListingFieldNames::all(),
            ...($schema === null ? [] : app(AttributeRules::class)->attributeNames($schema)),
        ];
    }

    /** Catégorie cible : celle envoyée, sinon celle du brouillon existant. */
    public function category(): ?Category
    {
        if ($this->resolvedCategory !== null) {
            return $this->resolvedCategory;
        }

        $id = $this->input('category_id');

        if (is_string($id) && Str::isUuid($id)) {
            return $this->resolvedCategory = Category::query()->find($id);
        }

        $listing = $this->route('listing');

        return $this->resolvedCategory = $listing instanceof Listing ? $listing->category : null;
    }

    /** @return Collection<string, AttributeDefinition>|null */
    public function schema(): ?Collection
    {
        $category = $this->category();

        return $category === null ? null : app(CategorySchema::class)->for($category);
    }

    private function leafCategoryRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $category = $this->category();

            if ($category !== null && ! $category->isLeaf()) {
                $fail('Choisissez une sous-catégorie : une annonce doit être déposée dans une catégorie finale.');
            }
        };
    }
}
