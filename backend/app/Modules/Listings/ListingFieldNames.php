<?php

namespace App\Modules\Listings;

/**
 * Libellés français des champs d'annonce pour les messages de validation.
 * Gardés ici (et non dans lang/fr/validation.php, régénéré par laravel-lang).
 */
final class ListingFieldNames
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'category_id' => 'catégorie',
            'type' => "type d'annonce",
            'title' => 'titre',
            'description' => 'description',
            'price' => 'prix',
            'location' => 'localisation',
            'location.lat' => 'latitude',
            'location.lng' => 'longitude',
            'hide_phone' => 'masquer mon numéro',
            'attribute_values' => 'caractéristiques',
            'photos' => 'photos',
        ];
    }
}
