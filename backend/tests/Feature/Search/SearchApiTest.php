<?php

namespace Tests\Feature\Search;

use App\Enums\ListingStatus;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Photo;
use App\Modules\Search\Index\ListingIndex;
use App\Modules\Search\OpenSearch\OpenSearchClient;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche de bout en bout contre un vrai OpenSearch (index préfixé « test_ »).
 * RECH-01 mots-clés tolérants · RECH-02 catégories · RECH-03 attributs · RECH-04 rayon · RECH-06 tris · RECH-07 filtres.
 */
class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    private OpenSearchClient $client;

    private ListingIndex $index;

    private Category $vehicules;

    private Category $voitures;

    private Category $velos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = app(OpenSearchClient::class);
        if (! $this->client->ping()) {
            $this->markTestSkipped('OpenSearch injoignable : docker compose up -d opensearch');
        }

        config(['search.sync' => true]);
        $this->index = app(ListingIndex::class);
        foreach ($this->client->aliasTargets($this->index->alias()) as $old) {
            $this->client->deleteIndex($old);
        }

        $this->vehicules = Category::factory()->create(['name' => 'Véhicules', 'slug' => 'vehicules']);
        $this->voitures = Category::factory()->childOf($this->vehicules)->create(['name' => 'Voitures', 'slug' => 'voitures']);
        $loisirs = Category::factory()->create(['name' => 'Loisirs', 'slug' => 'loisirs']);
        $this->velos = Category::factory()->childOf($loisirs)->create(['name' => 'Vélos', 'slug' => 'velos']);

        AttributeDefinition::factory()->for($this->voitures)->number(0, 2000000, 'km')->create(['key' => 'kilometrage', 'label' => 'Kilométrage']);
        AttributeDefinition::factory()->for($this->voitures)->select(['essence', 'diesel', 'electrique'])->create(['key' => 'carburant', 'label' => 'Carburant']);
        AttributeDefinition::factory()->for($this->voitures)->create(['key' => 'marque', 'label' => 'Marque', 'is_filterable' => false]);
    }

    protected function tearDown(): void
    {
        if (isset($this->index)) {
            foreach ($this->client->aliasTargets($this->index->alias()) as $old) {
                $this->client->deleteIndex($old);
            }
        }

        parent::tearDown();
    }

    /** Description neutre et fixe : un texte aléatoire pourrait correspondre (à une faute près) aux mots cherchés. */
    private function listing(array $attributes, ?Category $category = null): Listing
    {
        return Listing::factory()->active()->for($category ?? $this->voitures)->create([
            'description' => 'Annonce de démonstration, disponible rapidement.',
            ...$attributes,
        ]);
    }

    private function refresh(): void
    {
        $this->client->refresh($this->index->alias());
    }

    private function titles(string $query): array
    {
        $this->refresh();

        return collect($this->getJson('/api/v1/search?'.$query)->assertOk()->json('data'))->pluck('title')->all();
    }

    public function test_keywords_tolerate_typos_accents_plurals_and_synonyms(): void
    {
        $this->listing(['title' => 'Peugeot 208 très bon état']);
        $this->listing(['title' => 'Vélo de ville électrique'], $this->velos);
        $this->listing(['title' => 'Canapé d’angle en cuir'], $this->velos);

        $this->assertSame(['Peugeot 208 très bon état'], $this->titles('q=peugot+208'), 'faute de frappe');
        $this->assertSame(['Vélo de ville électrique'], $this->titles('q=velos'), 'accent et pluriel');
        $this->assertSame(['Vélo de ville électrique'], $this->titles('q=bicyclette'), 'synonyme');
        $this->assertSame(['Canapé d’angle en cuir'], $this->titles('q=sofa'), 'synonyme');
        $this->assertSame([], $this->titles('q=trottinette'));
    }

    public function test_only_public_listings_are_searchable_and_the_index_follows_status_changes(): void
    {
        $draft = Listing::factory()->for($this->voitures)->create(['title' => 'Clio brouillon']);
        $this->assertSame([], $this->titles('q=clio'));

        $draft->update(['status' => ListingStatus::Active, 'published_at' => now()]);
        $this->assertSame(['Clio brouillon'], $this->titles('q=clio'));

        $draft->update(['status' => ListingStatus::Sold]);
        $this->assertSame([], $this->titles('q=clio'));

        $other = $this->listing(['title' => 'Clio supprimée']);
        $other->delete();
        $this->assertSame([], $this->titles('q=clio'));
    }

    public function test_category_filter_includes_subcategories_and_returns_counts(): void
    {
        $this->listing(['title' => 'Golf 7']);
        $this->listing(['title' => 'Polo']);
        $this->listing(['title' => 'VTT'], $this->velos);

        $this->assertEqualsCanonicalizing(['Golf 7', 'Polo'], $this->titles('categorie=vehicules'));

        $facets = collect($this->getJson('/api/v1/search')->json('facets.categories'))->pluck('count', 'slug');
        $this->assertSame(2, $facets['vehicules']);
        $this->assertSame(2, $facets['voitures']);
        $this->assertSame(1, $facets['velos']);
        $this->assertSame(1, $facets['loisirs']);
    }

    public function test_attribute_filters_and_facets_come_from_the_category_schema(): void
    {
        $this->listing(['title' => 'Diesel récente', 'attribute_values' => ['carburant' => 'diesel', 'kilometrage' => 40000, 'marque' => 'Peugeot']]);
        $this->listing(['title' => 'Diesel ancienne', 'attribute_values' => ['carburant' => 'diesel', 'kilometrage' => 210000]]);
        $this->listing(['title' => 'Essence', 'attribute_values' => ['carburant' => 'essence', 'kilometrage' => 90000]]);

        $this->assertSame(['Diesel récente'], $this->titles('categorie=voitures&a[carburant]=diesel&a[kilometrage][max]=100000'));
        $this->assertEqualsCanonicalizing(['Diesel récente', 'Essence'], $this->titles('categorie=voitures&a[carburant][]=diesel&a[carburant][]=essence&a[kilometrage][max]=100000'));
        // Valeur hors schéma ou attribut non filtrable : ignorés.
        $this->assertCount(3, $this->titles('categorie=voitures&a[carburant]=charbon&a[marque]=Peugeot&a[inconnu]=1'));

        $facets = collect($this->getJson('/api/v1/search?categorie=voitures')->json('facets.attributes'))->keyBy('key');
        $this->assertSame(
            [['value' => 'essence', 'count' => 1], ['value' => 'diesel', 'count' => 2], ['value' => 'electrique', 'count' => 0]],
            $facets['carburant']['options'],
        );
        $this->assertSame(['min' => 40000, 'max' => 210000, 'count' => 3], $facets['kilometrage']['stats']);
        $this->assertSame('km', $facets['kilometrage']['unit']);
        $this->assertArrayNotHasKey('marque', $facets->all(), 'attribut non filtrable');
    }

    public function test_radius_search_distance_sort_and_approximate_distance(): void
    {
        $this->listing(['title' => 'À Paris', 'location' => new GeoPoint(48.8566, 2.3522)]);
        $this->listing(['title' => 'À Versailles', 'location' => new GeoPoint(48.8049, 2.1204)]);
        $this->listing(['title' => 'À Lyon', 'location' => new GeoPoint(45.7640, 4.8357)]);
        $this->refresh();

        $response = $this->getJson('/api/v1/search?lat=48.86&lng=2.35&rayon=20&tri=distance')->assertOk();

        $this->assertSame(['À Paris', 'À Versailles'], collect($response->json('data'))->pluck('title')->all());
        $this->assertSame([1, 18], collect($response->json('data'))->pluck('distance_km')->all());
        $this->assertArrayNotHasKey('location', $response->json('data.0'), 'position exacte jamais exposée');
        $this->assertSame('distance', $response->json('meta.sort'));
    }

    public function test_price_sorts_and_range(): void
    {
        $this->listing(['title' => 'Chère', 'price' => 15000]);
        $this->listing(['title' => 'Moyenne', 'price' => 8000]);
        $this->listing(['title' => 'Bon marché', 'price' => 2500]);

        $this->assertSame(['Bon marché', 'Moyenne', 'Chère'], $this->titles('tri=price_asc'));
        $this->assertSame(['Chère', 'Moyenne', 'Bon marché'], $this->titles('tri=price_desc'));
        $this->assertSame(['Moyenne'], $this->titles('prix_min=3000&prix_max=10000'));
        $this->assertSame(['min' => 2500, 'max' => 15000, 'count' => 3], $this->getJson('/api/v1/search')->json('facets.price'));
    }

    public function test_results_expose_the_main_photo_and_pagination(): void
    {
        $listing = $this->listing(['title' => 'Avec photo']);
        Photo::factory()->for($listing)->create();
        Photo::factory()->for($listing)->pending()->create(['position' => 1]);
        foreach (range(1, 3) as $i) {
            $this->listing(['title' => "Autre {$i}"]);
        }
        $this->refresh();

        $response = $this->getJson('/api/v1/search?q=photo')->assertOk();
        $this->assertStringEndsWith('/card.webp', $response->json('data.0.photo.card'));
        $this->assertSame(1, $response->json('data.0.photo_count'));
        $this->assertSame('Avec <mark>photo</mark>', $response->json('data.0.title_highlight'));

        $page = $this->getJson('/api/v1/search?par_page=2&page=2')->assertOk();
        $this->assertSame(['total' => 4, 'page' => 2, 'per_page' => 2, 'last_page' => 2, 'sort' => 'date'], $page->json('meta'));
        $this->assertCount(2, $page->json('data'));
    }

    public function test_autocomplete_suggests_categories_and_titles_from_two_characters(): void
    {
        $this->listing(['title' => 'Peugeot 208']);
        $this->listing(['title' => 'Peugeot 3008']);
        $this->refresh();

        $suggestions = collect($this->getJson('/api/v1/search/suggest?q=peu')->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing(['Peugeot 208', 'Peugeot 3008'], $suggestions->where('type', 'query')->pluck('label')->all());

        $this->assertSame(['Vélos'], collect($this->getJson('/api/v1/search/suggest?q=vel')->json('data'))->where('type', 'category')->pluck('label')->all());
        $this->getJson('/api/v1/search/suggest?q=p')->assertUnprocessable();
    }

    public function test_invalid_parameters_are_rejected_in_french(): void
    {
        $this->getJson('/api/v1/search?rayon=7&tri=hasard&categorie=inexistante')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rayon', 'tri', 'categorie']);
    }

    public function test_search_engine_outage_returns_a_clear_503(): void
    {
        config(['search.host' => 'http://127.0.0.1:1']);
        $this->app->forgetInstance(OpenSearchClient::class);

        $this->getJson('/api/v1/search?q=velo')
            ->assertStatus(503)
            ->assertJsonPath('message', 'La recherche est momentanément indisponible. Réessayez dans un instant.');
    }
}
