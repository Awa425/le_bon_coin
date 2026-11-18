<?php

namespace App\Modules\Search\Query;

use App\Support\Geo\GeoPoint;

/** Critères de recherche validés (RECH-01 à 07). */
final readonly class SearchCriteria
{
    public const SORTS = ['relevance', 'date', 'price_asc', 'price_desc', 'distance'];

    /**
     * @param  list<string>  $categoryIds  catégorie choisie (les sous-catégories sont incluses via category_ids)
     * @param  array<string, list<string>>  $keywordFilters  attributs liste / multi-choix / texte
     * @param  array<string, array{min?: float, max?: float}>  $rangeFilters  attributs numériques
     * @param  array<string, bool>  $booleanFilters
     * @param  list<string>  $facetKeywords  attributs « liste » dont on veut les compteurs
     * @param  list<string>  $facetRanges  attributs numériques dont on veut min / max
     */
    public function __construct(
        public ?string $query = null,
        public ?string $categoryId = null,
        public ?string $type = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public array $keywordFilters = [],
        public array $rangeFilters = [],
        public array $booleanFilters = [],
        public ?GeoPoint $around = null,
        public ?int $radiusKm = null,
        public ?string $sellerType = null,
        public bool $deliveryOnly = false,
        public bool $urgentOnly = false,
        public string $sort = 'relevance',
        public int $page = 1,
        public int $perPage = 24,
        public array $facetKeywords = [],
        public array $facetRanges = [],
    ) {}

    /** Tri effectif : « pertinence » sans mots-clés n'a pas de sens → plus récentes ; distance sans point → pertinence. */
    public function effectiveSort(): string
    {
        if ($this->sort === 'distance' && $this->around === null) {
            return $this->query ? 'relevance' : 'date';
        }

        if ($this->sort === 'relevance' && ($this->query === null || $this->query === '')) {
            return 'date';
        }

        return $this->sort;
    }
}
