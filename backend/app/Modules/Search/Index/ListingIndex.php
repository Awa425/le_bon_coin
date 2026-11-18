<?php

namespace App\Modules\Search\Index;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use App\Models\Listing;
use App\Modules\Catalog\Services\CategorySchema;
use Illuminate\Support\Collection;

/**
 * Index « listings » : analyse française et document indexé pour une annonce.
 *
 * Attributs dynamiques rangés par type (attributes.kw / .num / .bool) pour éviter les conflits
 * de mapping quand une même clé a des types différents selon la catégorie.
 */
class ListingIndex
{
    public function __construct(private readonly CategorySchema $schema) {}

    /** Alias stable interrogé par l'application ; il pointe vers un index versionné. */
    public function alias(): string
    {
        return config('search.prefix').'listings';
    }

    public function newVersionName(): string
    {
        return $this->alias().'_'.now()->format('YmdHisv');
    }

    public function definition(): array
    {
        return [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'max_result_window' => config('search.max_page') * config('search.max_per_page'),
                'analysis' => [
                    'filter' => [
                        'fr_elision' => [
                            'type' => 'elision',
                            'articles_case' => true,
                            'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu'],
                        ],
                        'fr_stop' => ['type' => 'stop', 'stopwords' => '_french_'],
                        // Pluriels et formes proches (« vélos » → « velo »).
                        'fr_stemmer' => ['type' => 'stemmer', 'language' => 'light_french'],
                        'fr_synonyms' => ['type' => 'synonym_graph', 'synonyms' => config('search.synonyms'), 'lenient' => true],
                        'autocomplete_ngram' => ['type' => 'edge_ngram', 'min_gram' => 2, 'max_gram' => 20],
                    ],
                    'analyzer' => [
                        // Accents ignorés (asciifolding) avant la racinisation.
                        'fr_index' => [
                            'tokenizer' => 'standard',
                            'filter' => ['fr_elision', 'lowercase', 'asciifolding', 'fr_stop', 'fr_stemmer'],
                        ],
                        'fr_search' => [
                            'tokenizer' => 'standard',
                            'filter' => ['fr_elision', 'lowercase', 'asciifolding', 'fr_synonyms', 'fr_stop', 'fr_stemmer'],
                        ],
                        'autocomplete_index' => [
                            'tokenizer' => 'standard',
                            'filter' => ['fr_elision', 'lowercase', 'asciifolding', 'autocomplete_ngram'],
                        ],
                        'autocomplete_search' => [
                            'tokenizer' => 'standard',
                            'filter' => ['fr_elision', 'lowercase', 'asciifolding'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'dynamic' => 'strict',
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'slug' => ['type' => 'keyword', 'index' => false],
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'fr_index',
                        'search_analyzer' => 'fr_search',
                        'fields' => [
                            'suggest' => ['type' => 'text', 'analyzer' => 'autocomplete_index', 'search_analyzer' => 'autocomplete_search'],
                            'raw' => ['type' => 'keyword', 'ignore_above' => 256],
                        ],
                    ],
                    'description' => ['type' => 'text', 'analyzer' => 'fr_index', 'search_analyzer' => 'fr_search'],
                    'type' => ['type' => 'keyword'],
                    'status' => ['type' => 'keyword'],
                    'category_id' => ['type' => 'keyword'],
                    // Catégorie et ses ancêtres : un filtre sur « Véhicules » trouve les « Voitures ».
                    'category_ids' => ['type' => 'keyword'],
                    'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                    'currency' => ['type' => 'keyword', 'index' => false],
                    'attributes' => [
                        'type' => 'object',
                        'properties' => [
                            'kw' => ['type' => 'object', 'dynamic' => true],
                            'num' => ['type' => 'object', 'dynamic' => true],
                            'bool' => ['type' => 'object', 'dynamic' => true],
                        ],
                    ],
                    'location' => ['type' => 'geo_point'],
                    'seller_type' => ['type' => 'keyword'],
                    'has_delivery' => ['type' => 'boolean'],
                    'is_urgent' => ['type' => 'boolean'],
                    'photo' => ['type' => 'object', 'enabled' => false],
                    'photo_count' => ['type' => 'integer'],
                    'published_at' => ['type' => 'date'],
                ],
                'dynamic_templates' => [
                    ['attr_kw' => ['path_match' => 'attributes.kw.*', 'mapping' => ['type' => 'keyword']]],
                    ['attr_num' => ['path_match' => 'attributes.num.*', 'mapping' => ['type' => 'double']]],
                    ['attr_bool' => ['path_match' => 'attributes.bool.*', 'mapping' => ['type' => 'boolean']]],
                ],
            ],
        ];
    }

    /** Seules les annonces publiques (active, réservée) sont présentes dans l'index. */
    public function shouldBeIndexed(Listing $listing): bool
    {
        return $listing->status->isPublic();
    }

    public function document(Listing $listing): array
    {
        $listing->loadMissing(['category', 'photos']);
        $category = $listing->category;
        $schema = $this->schema->for($category);
        $mainPhoto = $listing->photos->firstWhere(fn ($photo) => $photo->status->value === 'ready');

        return [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => (string) $listing->description,
            'type' => $listing->type->value,
            'status' => $listing->status->value,
            'category_id' => $category->id,
            'category_ids' => $category->ancestors()->push($category)->pluck('id')->values()->all(),
            'price' => $listing->price === null ? null : (float) $listing->price,
            'currency' => $listing->currency,
            'attributes' => $this->attributes($schema, $listing->attribute_values ?? []),
            'location' => $listing->location ? ['lat' => $listing->location->latitude, 'lon' => $listing->location->longitude] : null,
            // Comptes Pro (PRO-01), livraison (DEP-05) et option « urgent » (DEP-07) : valeurs par défaut en attendant.
            'seller_type' => 'private',
            'has_delivery' => false,
            'is_urgent' => false,
            'photo' => $mainPhoto ? [
                'thumb' => $mainPhoto->variantUrl('thumb'),
                'card' => $mainPhoto->variantUrl('card'),
                'card_avif' => $mainPhoto->variantUrl('card', 'avif'),
            ] : null,
            'photo_count' => $listing->photos->filter(fn ($photo) => $photo->status->value === 'ready')->count(),
            'published_at' => ($listing->published_at ?? $listing->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<string, AttributeDefinition>  $schema
     * @return array{kw: array<string, mixed>, num: array<string, float>, bool: array<string, bool>}
     */
    private function attributes(Collection $schema, array $values): array
    {
        $attributes = ['kw' => [], 'num' => [], 'bool' => []];

        foreach ($values as $key => $value) {
            $definition = $schema->get($key);
            if ($definition === null || $value === null) {
                continue;
            }

            match ($definition->type) {
                AttributeType::Number => $attributes['num'][$key] = (float) $value,
                AttributeType::Boolean => $attributes['bool'][$key] = (bool) $value,
                AttributeType::MultiSelect => $attributes['kw'][$key] = array_values((array) $value),
                default => $attributes['kw'][$key] = (string) $value,
            };
        }

        // Objets vides encodés en {} (et non []) pour respecter le mapping « object ».
        return array_map(fn (array $group) => (object) $group, $attributes);
    }
}
