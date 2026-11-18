<?php

namespace Tests\Unit;

use App\Casts\GeoPointCast;
use App\Enums\AttributeType;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class MarketplaceModelsTest extends TestCase
{
    public function test_marketplace_relations_resolve_to_the_expected_models_and_keys(): void
    {
        $relations = [
            [User::class, 'listings', Listing::class, 'user_id'],
            [User::class, 'buyerConversations', Conversation::class, 'buyer_id'],
            [User::class, 'sellerConversations', Conversation::class, 'seller_id'],
            [User::class, 'messages', Message::class, 'sender_id'],
            [Category::class, 'parent', Category::class, 'parent_id'],
            [Category::class, 'children', Category::class, 'parent_id'],
            [Category::class, 'attributeDefinitions', AttributeDefinition::class, 'category_id'],
            [Category::class, 'listings', Listing::class, 'category_id'],
            [AttributeDefinition::class, 'category', Category::class, 'category_id'],
            [Listing::class, 'user', User::class, 'user_id'],
            [Listing::class, 'category', Category::class, 'category_id'],
            [Listing::class, 'photos', Photo::class, 'listing_id'],
            [Listing::class, 'conversations', Conversation::class, 'listing_id'],
            [Photo::class, 'listing', Listing::class, 'listing_id'],
            [Conversation::class, 'listing', Listing::class, 'listing_id'],
            [Conversation::class, 'buyer', User::class, 'buyer_id'],
            [Conversation::class, 'seller', User::class, 'seller_id'],
            [Conversation::class, 'messages', Message::class, 'conversation_id'],
            [Message::class, 'conversation', Conversation::class, 'conversation_id'],
            [Message::class, 'sender', User::class, 'sender_id'],
        ];

        foreach ($relations as [$modelClass, $method, $relatedClass, $foreignKey]) {
            $relation = (new $modelClass)->{$method}();

            $this->assertSame($relatedClass, $relation->getRelated()::class, "{$modelClass}::{$method}");
            $this->assertSame($foreignKey, $relation->getForeignKeyName(), "{$modelClass}::{$method}");
            $this->assertContains($relation::class, [BelongsTo::class, HasMany::class]);
        }
    }

    public function test_marketplace_models_use_string_uuid_primary_keys(): void
    {
        foreach ([
            User::class,
            Category::class,
            AttributeDefinition::class,
            Listing::class,
            Photo::class,
            Conversation::class,
            Message::class,
        ] as $modelClass) {
            $model = new $modelClass;

            $this->assertFalse($model->getIncrementing(), $modelClass);
            $this->assertSame('string', $model->getKeyType(), $modelClass);
        }
    }

    public function test_dynamic_attribute_and_listing_status_enums_have_stable_values(): void
    {
        $this->assertSame(
            ['text', 'number', 'boolean', 'select', 'multi_select'],
            array_column(AttributeType::cases(), 'value'),
        );
        $this->assertSame(
            ['draft', 'pending_moderation', 'active', 'reserved', 'sold', 'refused', 'expired'],
            ListingStatus::values(),
        );
        $this->assertSame(['sale', 'rental', 'wanted', 'service', 'job'], ListingType::values());
    }

    public function test_json_and_enum_fields_are_cast_by_eloquent(): void
    {
        $this->assertSame(AttributeType::class, (new AttributeDefinition)->getCasts()['type']);
        $this->assertSame('array', (new AttributeDefinition)->getCasts()['options']);
        $this->assertSame(ListingStatus::class, (new Listing)->getCasts()['status']);
        $this->assertSame('array', (new Listing)->getCasts()['attribute_values']);
        $this->assertSame(GeoPointCast::class, (new Listing)->getCasts()['location']);
    }
}
