<?php

namespace Tests\Unit;

use App\Modules\Search\Query\ListingSearchQuery;
use App\Modules\Search\Query\SearchCriteria;
use App\Support\Geo\GeoPoint;
use PHPUnit\Framework\TestCase;

class ListingSearchQueryTest extends TestCase
{
    private function build(SearchCriteria $criteria): array
    {
        return (new ListingSearchQuery)->build($criteria);
    }

    public function test_without_keywords_it_matches_everything_and_sorts_by_date(): void
    {
        $body = $this->build(new SearchCriteria);

        $this->assertSame(['match_all' => []], json_decode(json_encode($body['query']['bool']['must'][0]), true));
        $this->assertSame([['terms' => ['status' => ['active', 'reserved']]]], $body['query']['bool']['filter']);
        $this->assertSame([['published_at' => ['order' => 'desc']], ['id' => 'asc']], $body['sort']);
        $this->assertArrayNotHasKey('highlight', $body);
        $this->assertSame(['excludes' => ['description']], $body['_source']);
    }

    public function test_keywords_are_fuzzy_and_exact_title_matches_are_boosted(): void
    {
        $body = $this->build(new SearchCriteria(query: 'peugot 208'));
        $should = $body['query']['bool']['must'][0]['bool']['should'];

        $this->assertSame('AUTO', $should[0]['multi_match']['fuzziness']);
        $this->assertSame(['title^3', 'description'], $should[0]['multi_match']['fields']);
        $this->assertSame(4, $should[1]['match_phrase']['title']['boost']);
        $this->assertSame(['_score', ['published_at' => ['order' => 'desc']]], $body['sort']);
        $this->assertArrayHasKey('title', $body['highlight']['fields']);
    }

    public function test_all_filters_are_translated(): void
    {
        $body = $this->build(new SearchCriteria(
            categoryId: 'cat-voitures',
            type: 'sale',
            priceMin: 1000,
            priceMax: null,
            keywordFilters: ['carburant' => ['diesel', 'essence']],
            rangeFilters: ['kilometrage' => ['max' => 100000]],
            booleanFilters: ['premiere_main' => true],
            around: new GeoPoint(48.85, 2.35),
            radiusKm: 20,
            sellerType: 'pro',
            deliveryOnly: true,
            urgentOnly: true,
        ));

        $this->assertSame([
            ['terms' => ['status' => ['active', 'reserved']]],
            ['term' => ['category_ids' => 'cat-voitures']],
            ['term' => ['type' => 'sale']],
            ['range' => ['price' => ['gte' => 1000.0]]],
            ['terms' => ['attributes.kw.carburant' => ['diesel', 'essence']]],
            ['range' => ['attributes.num.kilometrage' => ['lte' => 100000]]],
            ['term' => ['attributes.bool.premiere_main' => true]],
            ['geo_distance' => ['distance' => '20km', 'location' => ['lat' => 48.85, 'lon' => 2.35]]],
            ['term' => ['seller_type' => 'pro']],
            ['term' => ['has_delivery' => true]],
            ['term' => ['is_urgent' => true]],
        ], $body['query']['bool']['filter']);
    }

    public function test_sorts(): void
    {
        $paris = new GeoPoint(48.85, 2.35);

        $this->assertSame('asc', $this->build(new SearchCriteria(sort: 'price_asc'))['sort'][0]['price']['order']);
        $this->assertSame('_last', $this->build(new SearchCriteria(sort: 'price_desc'))['sort'][0]['price']['missing']);
        $this->assertSame(
            ['lat' => 48.85, 'lon' => 2.35],
            $this->build(new SearchCriteria(around: $paris, sort: 'distance'))['sort'][0]['_geo_distance']['location'],
        );
    }

    public function test_impossible_sorts_fall_back_sensibly(): void
    {
        $this->assertSame('date', (new SearchCriteria(sort: 'distance'))->effectiveSort(), 'distance sans point de référence');
        $this->assertSame('relevance', (new SearchCriteria(query: 'vélo', sort: 'distance'))->effectiveSort());
        $this->assertSame('date', (new SearchCriteria(sort: 'relevance'))->effectiveSort(), 'pertinence sans mots-clés');
    }

    public function test_pagination_and_facets(): void
    {
        $body = $this->build(new SearchCriteria(page: 3, perPage: 24, facetKeywords: ['carburant'], facetRanges: ['annee']));

        $this->assertSame(48, $body['from']);
        $this->assertSame(24, $body['size']);
        $this->assertSame('category_ids', $body['aggs']['categories']['terms']['field']);
        $this->assertSame('attributes.kw.carburant', $body['aggs']['kw_carburant']['terms']['field']);
        $this->assertSame('attributes.num.annee', $body['aggs']['num_annee']['stats']['field']);
        $this->assertTrue($body['track_total_hits']);
    }

    public function test_suggestions_use_prefixes_and_deduplicate_titles(): void
    {
        $body = (new ListingSearchQuery)->suggest('peu');

        $this->assertSame('bool_prefix', $body['query']['multi_match']['type']);
        $this->assertContains('title.suggest', $body['query']['multi_match']['fields']);
        $this->assertSame(['field' => 'title.raw'], $body['collapse']);
    }
}
