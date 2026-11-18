<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeDefinition>
 */
class AttributeDefinitionFactory extends Factory
{
    public function definition(): array
    {
        $key = fake()->unique()->lexify('attr_????');

        return [
            'category_id' => Category::factory(),
            'key' => $key,
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'type' => AttributeType::Text,
            'options' => [],
            'is_required' => false,
            'position' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function number(?float $min = null, ?float $max = null, ?string $unit = null): static
    {
        return $this->state(fn () => ['type' => AttributeType::Number, 'min_value' => $min, 'max_value' => $max, 'unit' => $unit]);
    }

    public function boolean(): static
    {
        return $this->state(fn () => ['type' => AttributeType::Boolean]);
    }

    /** @param  list<string>  $options */
    public function multiSelect(array $options): static
    {
        return $this->state(fn () => ['type' => AttributeType::MultiSelect, 'options' => $options]);
    }

    /** @param  list<string>  $options */
    public function select(array $options): static
    {
        return $this->state(fn () => ['type' => AttributeType::Select, 'options' => $options]);
    }
}
