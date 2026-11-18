<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'position' => 0,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
