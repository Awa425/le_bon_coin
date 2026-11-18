<?php

namespace App\Modules\Search\Http;

use App\Enums\AttributeType;
use App\Enums\ListingType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Modules\Catalog\Services\CategorySchema;
use App\Modules\Search\Query\SearchCriteria;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Paramètres d'URL de la recherche (tri et filtres conservés dans l'URL : partageable, indexable — RECH-06).
 *
 * q, categorie (slug), type, prix_min, prix_max, lat, lng, rayon, tri, page, par_page,
 * vendeur (particulier|pro), livraison (1), urgent (1),
 * a[<clé>]=valeur | a[<clé>][]=v1&a[<clé>][]=v2 | a[<clé>][min]=…&a[<clé>][max]=…
 */
class SearchRequest extends FormRequest
{
    private ?Category $category = null;

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'type' => ['nullable', Rule::enum(ListingType::class)],
            'prix_min' => ['nullable', 'numeric', 'min:0'],
            'prix_max' => ['nullable', 'numeric', 'min:0', 'gte:prix_min'],
            'lat' => ['nullable', 'required_with:lng,rayon', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat,rayon', 'numeric', 'between:-180,180'],
            'rayon' => ['nullable', 'integer', Rule::in(config('search.radii'))],
            'tri' => ['nullable', Rule::in(SearchCriteria::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.config('search.max_page')],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:'.config('search.max_per_page')],
            'vendeur' => ['nullable', Rule::in(['particulier', 'pro'])],
            'livraison' => ['nullable', 'boolean'],
            'urgent' => ['nullable', 'boolean'],
            'a' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'categorie' => 'catégorie', 'prix_min' => 'prix minimum', 'prix_max' => 'prix maximum',
            'rayon' => 'rayon', 'tri' => 'tri', 'vendeur' => 'type de vendeur',
        ];
    }

    public function category(): ?Category
    {
        $slug = $this->validated('categorie');

        return $slug ? ($this->category ??= Category::query()->where('slug', $slug)->first()) : null;
    }

    /** @return Collection<string, AttributeDefinition> */
    public function schema(): Collection
    {
        $category = $this->category();

        return $category ? app(CategorySchema::class)->for($category) : collect();
    }

    public function criteria(): SearchCriteria
    {
        $schema = $this->schema();
        [$keywords, $ranges, $booleans] = $this->attributeFilters($schema);
        $lat = $this->validated('lat');
        $lng = $this->validated('lng');
        $filterable = $schema->filter(fn (AttributeDefinition $d) => $d->is_filterable);

        return new SearchCriteria(
            query: trim((string) $this->validated('q')) ?: null,
            categoryId: $this->category()?->id,
            type: $this->validated('type'),
            priceMin: $this->optionalFloat('prix_min'),
            priceMax: $this->optionalFloat('prix_max'),
            keywordFilters: $keywords,
            rangeFilters: $ranges,
            booleanFilters: $booleans,
            around: $lat !== null && $lng !== null ? new GeoPoint((float) $lat, (float) $lng) : null,
            radiusKm: $this->validated('rayon') !== null ? (int) $this->validated('rayon') : null,
            sellerType: match ($this->validated('vendeur')) {
                'particulier' => 'private',
                'pro' => 'pro',
                default => null,
            },
            deliveryOnly: $this->boolean('livraison'),
            urgentOnly: $this->boolean('urgent'),
            sort: $this->validated('tri') ?? 'relevance',
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('par_page') ?? config('search.per_page')),
            facetKeywords: $filterable->filter(fn ($d) => $d->type->hasOptions())->keys()->values()->all(),
            facetRanges: $filterable->filter(fn ($d) => $d->type === AttributeType::Number)->keys()->values()->all(),
        );
    }

    /**
     * Filtres d'attributs, uniquement pour les attributs filtrables de la catégorie (clés inconnues ignorées).
     *
     * @param  Collection<string, AttributeDefinition>  $schema
     */
    private function attributeFilters(Collection $schema): array
    {
        $keywords = $ranges = $booleans = [];
        $input = (array) $this->input('a', []);

        foreach ($input as $key => $value) {
            $definition = $schema->get($key);
            if ($definition === null || ! $definition->is_filterable || $value === null || $value === '') {
                continue;
            }

            switch ($definition->type) {
                case AttributeType::Number:
                    $range = is_array($value) ? $value : ['min' => $value, 'max' => $value];
                    $bounds = array_filter([
                        'min' => is_numeric($range['min'] ?? null) ? (float) $range['min'] : null,
                        'max' => is_numeric($range['max'] ?? null) ? (float) $range['max'] : null,
                    ], fn ($v) => $v !== null);
                    if ($bounds !== []) {
                        $ranges[$key] = $bounds;
                    }
                    break;
                case AttributeType::Boolean:
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool !== null) {
                        $booleans[$key] = $bool;
                    }
                    break;
                default:
                    $values = array_values(array_filter(array_map('strval', (array) $value), fn ($v) => $v !== ''));
                    if ($definition->type->hasOptions()) {
                        $values = array_values(array_intersect($values, $definition->options));
                    }
                    if ($values !== []) {
                        $keywords[$key] = array_slice($values, 0, 20);
                    }
            }
        }

        return [$keywords, $ranges, $booleans];
    }

    private function optionalFloat(string $key): ?float
    {
        $value = $this->validated($key);

        return $value === null ? null : (float) $value;
    }
}
