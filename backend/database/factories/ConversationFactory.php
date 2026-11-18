<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'buyer_id' => User::factory(),
            // Le vendeur est toujours l'auteur de l'annonce.
            'seller_id' => fn (array $attributes) => Listing::query()->whereKey($attributes['listing_id'])->value('user_id'),
        ];
    }
}
