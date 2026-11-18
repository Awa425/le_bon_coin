<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttributeDefinition */
class AttributeDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'unit' => $this->unit,
            'options' => $this->type->hasOptions() ? $this->options : [],
            'min' => $this->min_value,
            'max' => $this->max_value,
            'required' => $this->is_required,
            'filterable' => $this->is_filterable,
        ];
    }
}
