<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DEP-01 : formulaire de dépôt adapté à la catégorie (champs, validations et obligatoires issus du schéma).
 */
class ListingDraftApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $voitures;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $vehicules = Category::factory()->create(['slug' => 'vehicules']);
        $this->voitures = Category::factory()->childOf($vehicules)->create(['slug' => 'voitures']);
        AttributeDefinition::factory()->for($this->voitures)->number(0, 2000000, 'km')->required()
            ->create(['key' => 'kilometrage', 'label' => 'Kilométrage', 'position' => 0]);
        AttributeDefinition::factory()->for($this->voitures)->select(['essence', 'diesel', 'electrique'])->required()
            ->create(['key' => 'carburant', 'label' => 'Carburant', 'position' => 1]);
        AttributeDefinition::factory()->for($this->voitures)->boolean()
            ->create(['key' => 'premiere_main', 'label' => 'Première main', 'position' => 2]);

        $this->seller = User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function completePayload(array $overrides = []): array
    {
        return array_replace([
            'category_id' => $this->voitures->id,
            'title' => 'Peugeot 208 très bon état',
            'description' => 'Entretien à jour, contrôle technique OK, aucun frais à prévoir.',
            'price' => 8990,
            'location' => ['lat' => 45.764, 'lng' => 4.8357],
            'attribute_values' => ['kilometrage' => '84000', 'carburant' => 'essence', 'premiere_main' => true],
        ], $overrides);
    }

    public function test_creating_a_listing_requires_authentication(): void
    {
        $this->postJson('/api/v1/listings', $this->completePayload())->assertUnauthorized();
    }

    public function test_a_seller_can_save_a_minimal_draft(): void
    {
        Sanctum::actingAs($this->seller);

        $response = $this->postJson('/api/v1/listings', [
            'category_id' => $this->voitures->id,
            'title' => 'Peugeot 208',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.hide_phone', true); // DEP-06 : téléphone masqué par défaut

        $listing = Listing::query()->sole();
        $this->assertTrue($listing->user->is($this->seller));
        $this->assertStringStartsWith('peugeot-208-', $listing->slug);
    }

    public function test_draft_values_are_typed_and_location_is_stored_in_postgis(): void
    {
        Sanctum::actingAs($this->seller);

        $id = $this->postJson('/api/v1/listings', $this->completePayload())
            ->assertCreated()
            ->assertJsonPath('data.attribute_values.kilometrage', 84000)
            ->assertJsonPath('data.location.lat', 45.764)
            ->json('data.id');

        $listing = Listing::query()->findOrFail($id);
        $this->assertSame(84000, $listing->attribute_values['kilometrage']);
        $this->assertEqualsWithDelta(4.8357, $listing->location->longitude, 1e-9);
    }

    public function test_attributes_are_validated_against_the_category_schema(): void
    {
        Sanctum::actingAs($this->seller);

        $this->postJson('/api/v1/listings', $this->completePayload([
            'attribute_values' => ['kilometrage' => -1, 'carburant' => 'charbon', 'surface' => 40],
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'attribute_values',
            'attribute_values.kilometrage',
            'attribute_values.carburant',
        ]);

        $this->assertSame(0, Listing::count());
    }

    public function test_validation_messages_use_attribute_labels(): void
    {
        Sanctum::actingAs($this->seller);

        $message = $this->postJson('/api/v1/listings', $this->completePayload(['attribute_values' => ['carburant' => 'charbon']]))
            ->json('errors')['attribute_values.carburant'][0];

        $this->assertStringContainsString('Carburant', $message);
    }

    public function test_a_listing_must_be_posted_in_a_leaf_category(): void
    {
        Sanctum::actingAs($this->seller);

        $this->postJson('/api/v1/listings', $this->completePayload([
            'category_id' => $this->voitures->parent_id,
            'attribute_values' => [],
        ]))->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_the_draft_can_be_autosaved_step_by_step(): void
    {
        Sanctum::actingAs($this->seller);
        $listing = Listing::factory()->for($this->seller)->for($this->voitures)->create(['attribute_values' => ['carburant' => 'diesel']]);

        $this->patchJson("/api/v1/listings/{$listing->id}", ['price' => 7500])
            ->assertOk()
            ->assertJsonPath('data.price', '7500.00')
            ->assertJsonPath('data.attribute_values.carburant', 'diesel');

        $this->patchJson("/api/v1/listings/{$listing->id}", ['attribute_values' => ['kilometrage' => 90000]])
            ->assertOk()
            ->assertJsonPath('data.attribute_values.kilometrage', 90000);
    }

    public function test_changing_category_drops_attributes_absent_from_the_new_schema(): void
    {
        Sanctum::actingAs($this->seller);
        $motos = Category::factory()->childOf($this->voitures->parent)->create();
        AttributeDefinition::factory()->for($motos)->number()->create(['key' => 'kilometrage']);
        $listing = Listing::factory()->for($this->seller)->for($this->voitures)
            ->create(['attribute_values' => ['kilometrage' => 1000, 'carburant' => 'diesel']]);

        $this->patchJson("/api/v1/listings/{$listing->id}", ['category_id' => $motos->id])->assertOk();

        $this->assertSame(['kilometrage' => 1000], $listing->fresh()->attribute_values);
    }

    public function test_another_user_cannot_edit_or_see_a_draft(): void
    {
        $listing = Listing::factory()->for($this->voitures)->create();
        Sanctum::actingAs($this->seller);

        $this->patchJson("/api/v1/listings/{$listing->id}", ['attribute_values' => ['carburant' => 'charbon']])->assertForbidden();
        $this->postJson("/api/v1/listings/{$listing->id}/submit")->assertForbidden();
        $this->getJson("/api/v1/listings/{$listing->id}")->assertForbidden();
    }

    public function test_submission_enforces_required_fields_and_attributes(): void
    {
        Sanctum::actingAs($this->seller);
        $listing = Listing::factory()->for($this->seller)->for($this->voitures)
            ->create(['description' => null, 'price' => null, 'location' => null, 'attribute_values' => ['carburant' => 'diesel']]);

        $this->postJson("/api/v1/listings/{$listing->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'price', 'location', 'attribute_values.kilometrage'])
            ->assertJsonMissingValidationErrors(['attribute_values.carburant', 'attribute_values.premiere_main']);

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_a_complete_draft_is_submitted_to_moderation_and_locked(): void
    {
        Sanctum::actingAs($this->seller);
        $id = $this->postJson('/api/v1/listings', $this->completePayload())->json('data.id');
        Photo::factory()->for(Listing::query()->findOrFail($id))->create();

        $this->postJson("/api/v1/listings/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_moderation')
            ->assertJsonCount(1, 'data.photos');

        $this->patchJson("/api/v1/listings/{$id}", ['price' => 1])->assertForbidden();
        $this->postJson("/api/v1/listings/{$id}/submit")->assertForbidden();
    }

    public function test_price_is_optional_for_wanted_listings(): void
    {
        Sanctum::actingAs($this->seller);
        $id = $this->postJson('/api/v1/listings', $this->completePayload(['type' => 'wanted', 'price' => null]))->json('data.id');

        $this->postJson("/api/v1/listings/{$id}/submit")->assertOk();
    }

    public function test_an_active_listing_is_public_but_hides_the_exact_location(): void
    {
        $listing = Listing::factory()->active()->for($this->voitures)->create();

        $this->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.location');
    }

    public function test_listing_creation_is_rate_limited(): void
    {
        Sanctum::actingAs($this->seller);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/listings', ['category_id' => $this->voitures->id, 'title' => "Annonce {$i}"])->assertCreated();
        }

        $this->postJson('/api/v1/listings', ['category_id' => $this->voitures->id, 'title' => 'Annonce 6'])->assertTooManyRequests();
    }
}
