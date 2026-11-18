<?php

namespace Database\Factories;

use App\Enums\PhotoStatus;
use App\Models\Listing;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /** Par défaut : photo traitée, avec ses déclinaisons. */
    public function definition(): array
    {
        $base = 'variants/'.fake()->uuid();

        return [
            'listing_id' => Listing::factory(),
            'status' => PhotoStatus::Ready,
            'original_path' => null,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 850_000,
            'width' => 1600,
            'height' => 1200,
            'variants' => collect(config('photos.sizes'))
                ->map(fn (int $width, string $name) => [
                    'width' => $width,
                    'webp' => "{$base}/{$name}.webp",
                    'avif' => "{$base}/{$name}.avif",
                ])->all(),
            'alt_text' => fake()->sentence(3),
            'position' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PhotoStatus::Pending,
            'original_path' => 'originals/'.fake()->uuid().'.jpg',
            'variants' => [],
            'width' => null,
            'height' => null,
            'size_bytes' => null,
        ]);
    }
}
