<?php

namespace App\Modules\Search;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Modules\Search\Index\ListingIndex;
use App\Modules\Search\OpenSearch\OpenSearchClient;
use App\Modules\Search\Query\ListingSearchQuery;
use App\Modules\Search\Query\SearchCriteria;
use App\Support\Geo\GeoPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Exécute une recherche et met en forme résultats + facettes pour l'API. */
class ListingSearch
{
    public function __construct(
        private readonly OpenSearchClient $client,
        private readonly ListingIndex $index,
        private readonly ListingSearchQuery $query,
    ) {}

    /** @param  Collection<string, AttributeDefinition>  $schema  schéma de la catégorie filtrée (facettes RECH-03) */
    public function search(SearchCriteria $criteria, Collection $schema): array
    {
        $response = $this->client->search($this->index->alias(), $this->query->build($criteria));
        $total = (int) ($response['hits']['total']['value'] ?? 0);

        return [
            'data' => array_map(fn (array $hit) => $this->hit($hit, $criteria->around), $response['hits']['hits'] ?? []),
            'meta' => [
                'total' => $total,
                'page' => $criteria->page,
                'per_page' => $criteria->perPage,
                'last_page' => min((int) max(1, ceil($total / $criteria->perPage)), (int) config('search.max_page')),
                'sort' => $criteria->effectiveSort(),
            ],
            'facets' => [
                'categories' => $this->categoryFacet($response['aggregations']['categories']['buckets'] ?? []),
                'price' => $this->stats($response['aggregations']['price'] ?? []),
                'attributes' => $this->attributeFacets($schema, $response['aggregations'] ?? []),
            ],
        ];
    }

    /** @return list<array{type: string, label: string, value: string}> */
    public function suggest(string $query): array
    {
        $suggestions = [];

        // Catégories dont le nom contient la saisie (accents et casse ignorés).
        $needle = $this->normalize($query);
        foreach (Category::query()->orderBy('name')->get(['id', 'name', 'slug']) as $category) {
            if (str_contains($this->normalize($category->name), $needle)) {
                $suggestions[] = ['type' => 'category', 'label' => $category->name, 'value' => $category->slug];
            }
        }

        $response = $this->client->search($this->index->alias(), $this->query->suggest($query));
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $suggestions[] = ['type' => 'query', 'label' => $hit['_source']['title'], 'value' => $hit['_source']['title']];
        }

        return array_slice($suggestions, 0, 10);
    }

    private function hit(array $hit, ?GeoPoint $around): array
    {
        $source = $hit['_source'];
        $distance = null;

        if ($around && isset($source['location']['lat'])) {
            $distance = $this->distanceKm($around, $source['location']['lat'], $source['location']['lon']);
        }

        return [
            'id' => $source['id'],
            'slug' => $source['slug'],
            'title' => $source['title'],
            'title_highlight' => $hit['highlight']['title'][0] ?? null,
            'type' => $source['type'],
            'status' => $source['status'],
            'category_id' => $source['category_id'],
            'price' => $source['price'],
            'currency' => $source['currency'],
            'photo' => $source['photo'],
            'photo_count' => $source['photo_count'],
            'seller_type' => $source['seller_type'],
            'has_delivery' => $source['has_delivery'],
            'is_urgent' => $source['is_urgent'],
            'published_at' => $source['published_at'],
            // Distance arrondie, jamais la position exacte (ANN-03 / anti-scraping).
            'distance_km' => $distance === null ? null : ($distance < 1 ? 1 : (int) round($distance)),
        ];
    }

    private function categoryFacet(array $buckets): array
    {
        $counts = collect($buckets)->pluck('doc_count', 'key');
        if ($counts->isEmpty()) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'count' => (int) $counts[$c->id],
            ])
            ->values()
            ->all();
    }

    /** @param  Collection<string, AttributeDefinition>  $schema */
    private function attributeFacets(Collection $schema, array $aggregations): array
    {
        $facets = [];

        foreach ($schema->filter(fn (AttributeDefinition $d) => $d->is_filterable) as $key => $definition) {
            $facet = ['key' => $key, 'label' => $definition->label, 'type' => $definition->type->value, 'unit' => $definition->unit];

            if (in_array($definition->type, [AttributeType::Select, AttributeType::MultiSelect], true)) {
                $counts = collect($aggregations["kw_{$key}"]['buckets'] ?? [])->pluck('doc_count', 'key');
                // Toutes les valeurs du schéma, dans leur ordre, avec leur compteur (0 si absent).
                $facet['options'] = array_map(
                    fn (string $option) => ['value' => $option, 'count' => (int) ($counts[$option] ?? 0)],
                    $definition->options,
                );
            } elseif ($definition->type === AttributeType::Number) {
                $facet['stats'] = $this->stats($aggregations["num_{$key}"] ?? []);
            } elseif ($definition->type !== AttributeType::Boolean) {
                continue; // texte libre : pas de facette
            }

            $facets[] = $facet;
        }

        return $facets;
    }

    private function stats(array $stats): ?array
    {
        return isset($stats['count']) && $stats['count'] > 0
            ? ['min' => $stats['min'], 'max' => $stats['max'], 'count' => (int) $stats['count']]
            : null;
    }

    private function distanceKm(GeoPoint $from, float $lat, float $lon): float
    {
        $rad = M_PI / 180;
        $a = sin(($lat - $from->latitude) * $rad / 2) ** 2
            + cos($from->latitude * $rad) * cos($lat * $rad) * sin(($lon - $from->longitude) * $rad / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }
}
