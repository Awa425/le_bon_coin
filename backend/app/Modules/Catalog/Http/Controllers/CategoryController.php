<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Modules\Catalog\Http\Resources\AttributeDefinitionResource;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Services\CategorySchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /** Arborescence complète (3 niveaux maximum), pour la navigation et le choix de catégorie au dépôt. */
    public function index(): AnonymousResourceCollection
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->with('children.children')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($roots);
    }

    /** Détail d'une catégorie : fil d'Ariane, sous-catégories et schéma d'attributs effectif (formulaire et filtres). */
    public function show(Category $category, CategorySchema $schema): JsonResponse
    {
        $category->load('children');

        return response()->json([
            'data' => [
                ...(new CategoryResource($category))->resolve(),
                'is_leaf' => $category->children->isEmpty(),
                'breadcrumb' => $category->ancestors()->push($category)
                    ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
                    ->values(),
                'attributes' => AttributeDefinitionResource::collection($schema->for($category)->values())->resolve(),
            ],
        ]);
    }
}
