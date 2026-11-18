<?php

namespace Tests\Feature;

use App\Enums\AttributeType;
use App\Enums\ListingStatus;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Photo;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exécute les migrations sur PostgreSQL/PostGIS et vérifie types, contraintes et relations.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function columnType(string $table, string $column): ?string
    {
        return DB::scalar(
            'SELECT format_type(a.atttypid, a.atttypmod) FROM pg_attribute a
             WHERE a.attrelid = ?::regclass AND a.attname = ? AND NOT a.attisdropped',
            [$table, $column],
        );
    }

    public function test_pivot_tables_use_uuid_primary_keys(): void
    {
        foreach (['users', 'categories', 'attribute_definitions', 'listings', 'photos', 'conversations', 'messages'] as $table) {
            $this->assertSame('uuid', $this->columnType($table, 'id'), $table);
        }
    }

    public function test_dynamic_attributes_are_jsonb_and_location_is_postgis_geography(): void
    {
        $this->assertSame('jsonb', $this->columnType('listings', 'attribute_values'));
        $this->assertSame('jsonb', $this->columnType('attribute_definitions', 'options'));
        $this->assertSame('geography(Point,4326)', $this->columnType('listings', 'location'));

        $indexes = DB::table('pg_indexes')->where('tablename', 'listings')->pluck('indexdef')->implode("\n");
        $this->assertStringContainsString('USING gist (location)', $indexes);
        $this->assertStringContainsString('USING gin (attribute_values jsonb_path_ops)', $indexes);
    }

    public function test_listing_round_trips_jsonb_attributes_enum_and_location(): void
    {
        $listing = Listing::factory()->active()->create([
            'attribute_values' => ['kilometrage' => 120000, 'carburant' => 'diesel'],
            'location' => new GeoPoint(48.8566, 2.3522),
        ])->fresh();

        $this->assertTrue(Str::isUuid($listing->id));
        $this->assertSame(ListingStatus::Active, $listing->status);
        $this->assertEqualsCanonicalizing(['kilometrage' => 120000, 'carburant' => 'diesel'], $listing->attribute_values);
        $this->assertEqualsWithDelta(48.8566, $listing->location->latitude, 1e-9);
        $this->assertEqualsWithDelta(2.3522, $listing->location->longitude, 1e-9);

        $this->assertSame(1, Listing::query()->whereJsonContains('attribute_values->carburant', 'diesel')->count());
    }

    public function test_listings_can_be_searched_by_distance(): void
    {
        Listing::factory()->create(['title' => 'Paris', 'location' => new GeoPoint(48.8566, 2.3522)]);
        Listing::factory()->create(['title' => 'Versailles', 'location' => new GeoPoint(48.8049, 2.1204)]);
        Listing::factory()->create(['title' => 'Lyon', 'location' => new GeoPoint(45.7640, 4.8357)]);

        $titles = Listing::query()
            ->whereRaw('ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)', [2.3522, 48.8566, 30_000])
            ->orderBy('title')
            ->pluck('title')
            ->all();

        $this->assertSame(['Paris', 'Versailles'], $titles);
    }

    public function test_relations_persist_across_the_whole_graph(): void
    {
        $parent = Category::factory()->create();
        $category = Category::factory()->childOf($parent)->create();
        AttributeDefinition::factory()->for($category)->select(['essence', 'diesel'])->create(['key' => 'carburant']);
        $listing = Listing::factory()->for($category)->create();
        Photo::factory()->for($listing)->count(2)->sequence(['position' => 1], ['position' => 0])->create();
        $conversation = Conversation::factory()->for($listing)->create();
        Message::factory()->for($conversation)->count(3)->create();

        $this->assertTrue($category->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($category));
        $this->assertSame(AttributeType::Select, $category->attributeDefinitions->first()->type);
        $this->assertSame(['essence', 'diesel'], $category->attributeDefinitions->first()->options);
        $this->assertSame([0, 1], $listing->photos->pluck('position')->all());
        $this->assertTrue($conversation->seller->is($listing->user));
        $this->assertCount(3, $conversation->messages);
        $this->assertCount(1, $listing->user->sellerConversations);
        $this->assertCount(1, $conversation->buyer->buyerConversations);
        $this->assertCount(3, $conversation->buyer->messages);
    }

    public function test_a_user_cannot_open_a_conversation_with_themself(): void
    {
        $listing = Listing::factory()->create();

        $this->expectException(QueryException::class);

        Conversation::factory()->for($listing)->create(['buyer_id' => $listing->user_id]);
    }

    public function test_only_one_conversation_per_buyer_and_listing(): void
    {
        $conversation = Conversation::factory()->create();

        $this->expectException(QueryException::class);

        Conversation::factory()->create([
            'listing_id' => $conversation->listing_id,
            'buyer_id' => $conversation->buyer_id,
        ]);
    }

    public function test_price_cannot_be_negative(): void
    {
        $this->expectException(QueryException::class);

        Listing::factory()->create(['price' => -1]);
    }

    public function test_a_category_with_listings_cannot_be_deleted(): void
    {
        $listing = Listing::factory()->create();

        $this->expectException(QueryException::class);

        $listing->category->delete();
    }

    public function test_deleting_a_parent_category_detaches_its_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_deleting_a_user_cascades_to_their_listings_photos_and_conversations(): void
    {
        $conversation = Conversation::factory()->create();
        Photo::factory()->for($conversation->listing)->create();
        Message::factory()->for($conversation)->create();

        $conversation->seller->delete();

        $this->assertSame(0, Listing::count());
        $this->assertSame(0, Photo::count());
        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());
    }
}
