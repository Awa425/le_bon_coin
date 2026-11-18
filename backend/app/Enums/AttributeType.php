<?php

namespace App\Enums;

/**
 * Types d'attributs dynamiques (RECH-03 / BO-05) : texte, plage numérique, liste, multi-choix, booléen.
 */
enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multi_select';

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
