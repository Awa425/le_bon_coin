<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'position'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;

    /** Profondeur maximale de l'arborescence (RECH-02). */
    public const MAX_DEPTH = 3;

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if ($category->parent_id === null) {
                return;
            }

            if ($category->exists && $category->parent_id === $category->id) {
                throw new DomainException('Une catégorie ne peut pas être son propre parent.');
            }

            $parent = self::query()->findOrFail($category->parent_id);

            if ($category->exists && $parent->ancestors()->push($parent)->contains('id', $category->id)) {
                throw new DomainException("Cycle détecté dans l'arborescence des catégories.");
            }

            if ($parent->depth() + 1 + $category->subtreeHeight() > self::MAX_DEPTH) {
                throw new DomainException("L'arborescence des catégories est limitée à ".self::MAX_DEPTH.' niveaux.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(AttributeDefinition::class)->orderBy('position');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Ancêtres, de la racine vers le parent direct.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $current = $this->parent_id ? self::query()->find($this->parent_id) : null;

        while ($current !== null && $ancestors->count() < self::MAX_DEPTH) {
            $ancestors->prepend($current);
            $current = $current->parent_id ? self::query()->find($current->parent_id) : null;
        }

        return $ancestors;
    }

    /** Niveau dans l'arbre : 1 pour une racine. */
    public function depth(): int
    {
        return $this->ancestors()->count() + 1;
    }

    /** Nombre de niveaux sous cette catégorie (0 pour une feuille ou une catégorie non enregistrée). */
    private function subtreeHeight(): int
    {
        if (! $this->exists) {
            return 0;
        }

        $height = 0;
        $levelIds = [$this->id];

        while ($height <= self::MAX_DEPTH) {
            $levelIds = self::query()->whereIn('parent_id', $levelIds)->pluck('id')->all();

            if ($levelIds === []) {
                break;
            }

            $height++;
        }

        return $height;
    }

    /** Seules les catégories feuilles peuvent recevoir des annonces. */
    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
    }
}
