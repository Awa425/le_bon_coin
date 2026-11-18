<?php

namespace Tests\Feature;

use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** L'API répond en français (contenu français au lancement, cahier des charges §6.5). */
class FrenchMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_locale_is_french(): void
    {
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_failed_login_message_is_in_french(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'inconnu@example.fr', 'password' => 'x'])
            ->assertJsonPath('errors.email.0', 'Ces identifiants ne correspondent pas à nos enregistrements.');
    }

    public function test_registration_errors_are_in_french(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertJsonPath('errors.name.0', 'Le champ nom est obligatoire.');
    }

    public function test_listing_validation_uses_french_field_names(): void
    {
        $category = Category::factory()->create();
        AttributeDefinition::factory()->for($category)->number()->required()->create(['key' => 'kilometrage', 'label' => 'Kilométrage']);
        $listing = Listing::factory()->for($category)->create(['description' => null, 'location' => null]);
        Sanctum::actingAs($listing->user);

        $errors = $this->postJson("/api/v1/listings/{$listing->id}/submit")->assertUnprocessable()->json('errors');

        $this->assertSame('Le champ description est obligatoire.', $errors['description'][0]);
        $this->assertSame('Le champ localisation est obligatoire.', $errors['location'][0]);
        $this->assertSame('Le champ Kilométrage est obligatoire.', $errors['attribute_values.kilometrage'][0]);
    }

    public function test_draft_errors_name_the_category_field_in_french(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/listings', ['title' => 'Vélo'])
            ->assertJsonPath('errors.category_id.0', 'Le champ catégorie est obligatoire.');
    }
}
