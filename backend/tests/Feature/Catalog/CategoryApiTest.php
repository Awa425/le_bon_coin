<?php

namespace Tests\Feature\Catalog;

use App\Models\AttributeDefinition;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_is_returned_ordered_with_nested_children(): void
    {
        $vehicules = Category::factory()->create(['name' => 'Véhicules', 'slug' => 'vehicules', 'position' => 1]);
        Category::factory()->create(['name' => 'Immobilier', 'slug' => 'immobilier', 'position' => 0]);
        $voitures = Category::factory()->childOf($vehicules)->create(['name' => 'Voitures', 'slug' => 'voitures']);
        Category::factory()->childOf($voitures)->create(['name' => 'Citadines', 'slug' => 'citadines']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'immobilier')
            ->assertJsonPath('data.1.slug', 'vehicules')
            ->assertJsonPath('data.1.children.0.slug', 'voitures')
            ->assertJsonPath('data.1.children.0.children.0.slug', 'citadines');
    }

    public function test_category_detail_exposes_breadcrumb_and_inherited_attribute_schema(): void
    {
        $mode = Category::factory()->create(['name' => 'Mode', 'slug' => 'mode']);
        $vetements = Category::factory()->childOf($mode)->create(['name' => 'Vêtements', 'slug' => 'vetements']);
        AttributeDefinition::factory()->for($mode)->select(['neuf', 'bon_etat'])->required()->create(['key' => 'etat', 'label' => 'État']);
        AttributeDefinition::factory()->for($vetements)->select(['S', 'M', 'L'])->create(['key' => 'taille', 'label' => 'Taille']);

        $this->getJson('/api/v1/categories/vetements')
            ->assertOk()
            ->assertJsonPath('data.is_leaf', true)
            ->assertJsonPath('data.breadcrumb.0.slug', 'mode')
            ->assertJsonPath('data.breadcrumb.1.slug', 'vetements')
            ->assertJsonPath('data.attributes.0.key', 'etat')
            ->assertJsonPath('data.attributes.0.required', true)
            ->assertJsonPath('data.attributes.0.options', ['neuf', 'bon_etat'])
            ->assertJsonPath('data.attributes.1.key', 'taille');
    }

    public function test_a_child_definition_overrides_the_inherited_one(): void
    {
        $parent = Category::factory()->create(['slug' => 'parent']);
        $child = Category::factory()->childOf($parent)->create(['slug' => 'enfant']);
        AttributeDefinition::factory()->for($parent)->create(['key' => 'etat', 'label' => 'État général']);
        AttributeDefinition::factory()->for($child)->boolean()->create(['key' => 'etat', 'label' => 'Fonctionne']);

        $this->getJson('/api/v1/categories/enfant')
            ->assertJsonCount(1, 'data.attributes')
            ->assertJsonPath('data.attributes.0.label', 'Fonctionne')
            ->assertJsonPath('data.attributes.0.type', 'boolean');
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->getJson('/api/v1/categories/inexistante')->assertNotFound();
    }

    public function test_tree_depth_is_limited_to_three_levels(): void
    {
        $level1 = Category::factory()->create();
        $level2 = Category::factory()->childOf($level1)->create();
        $level3 = Category::factory()->childOf($level2)->create();

        $this->expectException(DomainException::class);

        Category::factory()->childOf($level3)->create();
    }

    public function test_moving_a_subtree_cannot_exceed_the_depth_limit_nor_create_a_cycle(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->childOf($a)->create();
        $other = Category::factory()->create();
        $otherChild = Category::factory()->childOf($other)->create();

        try {
            $other->update(['parent_id' => $b->id]); // donnerait 4 niveaux
            $this->fail('Profondeur dépassée non détectée.');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        $a->update(['parent_id' => $b->id]); // cycle
    }

    public function test_the_seeded_catalogue_respects_the_specification(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class); // idempotent

        $this->assertSame(9, Category::query()->whereNull('parent_id')->count());

        $this->getJson('/api/v1/categories/voitures')
            ->assertOk()
            ->assertJsonPath('data.attributes.2.key', 'kilometrage')
            ->assertJsonPath('data.attributes.2.unit', 'km');
    }
}
