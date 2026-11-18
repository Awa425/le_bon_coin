<?php

namespace App\Modules\Catalog\Services;

use App\Models\AttributeDefinition;
use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Schéma d'attributs effectif d'une catégorie : attributs hérités des ancêtres
 * (ex. « état » défini sur « Mode ») puis attributs propres, une définition
 * enfant remplaçant celle d'un ancêtre ayant la même clé.
 */
class CategorySchema
{
    /** @return Collection<string, AttributeDefinition> indexée par clé d'attribut */
    public function for(Category $category): Collection
    {
        $lineage = $category->ancestors()->push($category);

        $definitions = AttributeDefinition::query()
            ->whereIn('category_id', $lineage->pluck('id'))
            ->get()
            ->groupBy('category_id');

        $schema = collect();

        foreach ($lineage as $level) {
            foreach ($definitions->get($level->id, collect())->sortBy('position') as $definition) {
                $schema->put($definition->key, $definition);
            }
        }

        return $schema;
    }
}
