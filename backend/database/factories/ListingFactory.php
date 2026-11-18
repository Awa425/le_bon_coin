<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'type' => ListingType::Sale,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 5000),
            'currency' => 'EUR',
            'status' => ListingStatus::Draft,
            'attribute_values' => [],
            // Coordonnées en France métropolitaine.
            'location' => new GeoPoint(fake()->latitude(43, 50), fake()->longitude(-1, 7)),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Active,
            'published_at' => now(),
            'expires_at' => now()->addDays(Listing::LIFETIME_DAYS),
        ]);
    }
}
