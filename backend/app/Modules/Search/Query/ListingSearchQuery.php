<?php

namespace App\Modules\Search\Query;

/**
 * Traduit des critères en requête OpenSearch (fonction pure, testable sans serveur).
 */
class ListingSearchQuery
{
    public function build(SearchCriteria $criteria): array
    {
        $filters = $this->filters($criteria);

        $body = [
            'track_total_hits' => true,
            'from' => ($criteria->page - 1) * $criteria->perPage,
            'size' => $criteria->perPage,
            'query' => [
                'bool' => [
                    'must' => [$this->textQuery($criteria)],
                    'filter' => $filters,
                ],
            ],
            'sort' => $this->sort($criteria),
            '_source' => ['excludes' => ['description']],
            'aggs' => $this->aggregations($criteria),
        ];

        if ($criteria->query) {
            $body['highlight'] = [
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
                'fields' => ['title' => ['number_of_fragments' => 0]],
            ];
        }

        return $body;
    }

    /** Autocomplétion (RECH-01) : préfixes du titre, dès 2 caractères. */
    public function suggest(string $query, int $size = 8): array
    {
        return [
            'size' => $size,
            '_source' => ['title', 'category_id'],
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'type' => 'bool_prefix',
                    'fields' => ['title.suggest', 'title'],
                    'operator' => 'and',
                ],
            ],
            'collapse' => ['field' => 'title.raw'],
        ];
    }

    private function textQuery(SearchCriteria $criteria): array
    {
        if (! $criteria->query) {
            return ['match_all' => (object) []];
        }

        return [
            'bool' => [
                'should' => [
                    // Tolérance aux fautes (1 à 2 caractères selon la longueur du mot).
                    [
                        'multi_match' => [
                            'query' => $criteria->query,
                            'fields' => ['title^3', 'description'],
                            'fuzziness' => 'AUTO',
                            'prefix_length' => 1,
                            'operator' => 'and',
                        ],
                    ],
                    // Correspondance exacte du titre : bien mieux classée.
                    ['match_phrase' => ['title' => ['query' => $criteria->query, 'boost' => 4]]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function filters(SearchCriteria $criteria): array
    {
        $filters = [['terms' => ['status' => ['active', 'reserved']]]];

        if ($criteria->categoryId) {
            $filters[] = ['term' => ['category_ids' => $criteria->categoryId]];
        }
        if ($criteria->type) {
            $filters[] = ['term' => ['type' => $criteria->type]];
        }
        if ($criteria->priceMin !== null || $criteria->priceMax !== null) {
            $filters[] = ['range' => ['price' => array_filter(['gte' => $criteria->priceMin, 'lte' => $criteria->priceMax], fn ($v) => $v !== null)]];
        }
        foreach ($criteria->keywordFilters as $key => $values) {
            $filters[] = ['terms' => ["attributes.kw.{$key}" => array_values($values)]];
        }
        foreach ($criteria->rangeFilters as $key => $range) {
            $bounds = array_filter(['gte' => $range['min'] ?? null, 'lte' => $range['max'] ?? null], fn ($v) => $v !== null);
            if ($bounds !== []) {
                $filters[] = ['range' => ["attributes.num.{$key}" => $bounds]];
            }
        }
        foreach ($criteria->booleanFilters as $key => $value) {
            $filters[] = ['term' => ["attributes.bool.{$key}" => $value]];
        }
        if ($criteria->around && $criteria->radiusKm) {
            $filters[] = ['geo_distance' => [
                'distance' => $criteria->radiusKm.'km',
                'location' => ['lat' => $criteria->around->latitude, 'lon' => $criteria->around->longitude],
            ]];
        }
        if ($criteria->sellerType) {
            $filters[] = ['term' => ['seller_type' => $criteria->sellerType]];
        }
        if ($criteria->deliveryOnly) {
            $filters[] = ['term' => ['has_delivery' => true]];
        }
        if ($criteria->urgentOnly) {
            $filters[] = ['term' => ['is_urgent' => true]];
        }

        return $filters;
    }

    private function sort(SearchCriteria $criteria): array
    {
        $recent = ['published_at' => ['order' => 'desc']];

        return match ($criteria->effectiveSort()) {
            'date' => [$recent, ['id' => 'asc']],
            'price_asc' => [['price' => ['order' => 'asc', 'missing' => '_last']], $recent],
            'price_desc' => [['price' => ['order' => 'desc', 'missing' => '_last']], $recent],
            'distance' => [[
                '_geo_distance' => [
                    'location' => ['lat' => $criteria->around->latitude, 'lon' => $criteria->around->longitude],
                    'order' => 'asc',
                    'unit' => 'km',
                ],
            ], $recent],
            default => ['_score', $recent],
        };
    }

    private function aggregations(SearchCriteria $criteria): array
    {
        $aggs = [
            // Compteurs par catégorie (RECH-02) : chaque annonce compte pour sa catégorie et ses ancêtres.
            'categories' => ['terms' => ['field' => 'category_ids', 'size' => 200]],
            'price' => ['stats' => ['field' => 'price']],
        ];

        foreach ($criteria->facetKeywords as $key) {
            $aggs["kw_{$key}"] = ['terms' => ['field' => "attributes.kw.{$key}", 'size' => 50]];
        }
        foreach ($criteria->facetRanges as $key) {
            $aggs["num_{$key}"] = ['stats' => ['field' => "attributes.num.{$key}"]];
        }

        return $aggs;
    }
}
