<?php

namespace App\Models;

use App\Enums\AttributeType;
use Database\Factories\AttributeDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'key', 'label', 'type', 'unit', 'options', 'min_value', 'max_value', 'is_required', 'is_filterable', 'position'])]
class AttributeDefinition extends Model
{
    /** @use HasFactory<AttributeDefinitionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'options' => 'array',
            'min_value' => 'float',
            'max_value' => 'float',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
