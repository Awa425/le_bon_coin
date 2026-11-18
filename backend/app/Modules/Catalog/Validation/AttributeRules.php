<?php

namespace App\Modules\Catalog\Validation;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Traduit le schéma d'attributs d'une catégorie en règles de validation Laravel (DEP-01).
 *
 * - Mode brouillon : seuls les types et bornes sont contrôlés (sauvegarde automatique du tunnel).
 * - Mode publication : les attributs obligatoires deviennent requis.
 * Dans les deux cas, une clé absente du schéma est refusée.
 */
class AttributeRules
{
    public const FIELD = 'attribute_values';

    /**
     * @param  Collection<string, AttributeDefinition>  $schema
     * @return array<string, list<mixed>>
     */
    public function rules(Collection $schema, bool $enforceRequired): array
    {
        $rules = [self::FIELD => ['array:'.$schema->keys()->implode(',')]];

        if ($enforceRequired && $schema->contains(fn (AttributeDefinition $d) => $d->is_required)) {
            $rules[self::FIELD][] = 'required';
        } else {
            $rules[self::FIELD][] = 'nullable';
        }

        foreach ($schema as $key => $definition) {
            $field = self::FIELD.'.'.$key;
            $presence = $enforceRequired && $definition->is_required ? 'required' : 'nullable';

            $rules[$field] = [$presence, ...$this->typeRules($definition)];

            if ($definition->type === AttributeType::MultiSelect) {
                $rules[$field.'.*'] = ['string', 'distinct', Rule::in($definition->options)];
            }
        }

        return $rules;
    }

    /**
     * Libellés lisibles pour les messages d'erreur (« Kilométrage » plutôt que « attribute_values.kilometrage »).
     *
     * @param  Collection<string, AttributeDefinition>  $schema
     * @return array<string, string>
     */
    public function attributeNames(Collection $schema): array
    {
        return $schema->mapWithKeys(fn (AttributeDefinition $d, string $key) => [self::FIELD.'.'.$key => $d->label])->all();
    }

    /**
     * Normalise les valeurs validées selon leur type (« 12000 » → 12000, « 1 » → true) et retire les valeurs vides.
     *
     * @param  Collection<string, AttributeDefinition>  $schema
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function normalize(Collection $schema, array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $definition = $schema->get($key);

            if ($definition === null || $value === null || $value === '' || $value === []) {
                continue;
            }

            $normalized[$key] = match ($definition->type) {
                AttributeType::Number => $value + 0,
                AttributeType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                AttributeType::MultiSelect => array_values($value),
                default => $value,
            };
        }

        return $normalized;
    }

    /** @return list<mixed> */
    private function typeRules(AttributeDefinition $definition): array
    {
        return match ($definition->type) {
            AttributeType::Text => ['string', 'max:255'],
            AttributeType::Number => array_values(array_filter([
                'numeric',
                $definition->min_value !== null ? 'min:'.$definition->min_value : null,
                $definition->max_value !== null ? 'max:'.$definition->max_value : null,
            ])),
            AttributeType::Boolean => ['boolean'],
            AttributeType::Select => ['string', Rule::in($definition->options)],
            AttributeType::MultiSelect => ['array', 'min:1'],
        };
    }
}
