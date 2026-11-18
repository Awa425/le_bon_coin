<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalogue de départ (cahier des charges §1.1 et RECH-03). Idempotent : relançable sans doublon.
 * Les catégories et attributs seront ensuite gérés depuis le back-office (BO-05).
 */
class CategorySeeder extends Seeder
{
    private const ETAT = ['key' => 'etat', 'label' => 'État', 'type' => AttributeType::Select, 'required' => true,
        'options' => ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_satisfaisant', 'pour_pieces']];

    public function run(): void
    {
        $tree = [
            'Immobilier' => [
                'attributes' => [
                    ['key' => 'surface', 'label' => 'Surface', 'type' => AttributeType::Number, 'unit' => 'm²', 'min' => 1, 'max' => 100000, 'required' => true],
                    ['key' => 'pieces', 'label' => 'Pièces', 'type' => AttributeType::Number, 'min' => 1, 'max' => 50],
                    ['key' => 'dpe', 'label' => 'Classe énergie (DPE)', 'type' => AttributeType::Select, 'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G']],
                ],
                'children' => ['Ventes immobilières' => [], 'Locations' => [
                    'attributes' => [['key' => 'meuble', 'label' => 'Meublé', 'type' => AttributeType::Boolean]],
                ], 'Colocations' => []],
            ],
            'Véhicules' => [
                'children' => [
                    'Voitures' => ['attributes' => [
                        ['key' => 'marque', 'label' => 'Marque', 'type' => AttributeType::Text, 'required' => true],
                        ['key' => 'annee', 'label' => 'Année', 'type' => AttributeType::Number, 'min' => 1900, 'max' => 2100, 'required' => true],
                        ['key' => 'kilometrage', 'label' => 'Kilométrage', 'type' => AttributeType::Number, 'unit' => 'km', 'min' => 0, 'max' => 2000000, 'required' => true],
                        ['key' => 'carburant', 'label' => 'Carburant', 'type' => AttributeType::Select, 'required' => true,
                            'options' => ['essence', 'diesel', 'hybride', 'electrique', 'gpl']],
                        ['key' => 'boite', 'label' => 'Boîte de vitesse', 'type' => AttributeType::Select, 'options' => ['manuelle', 'automatique']],
                    ]],
                    'Motos' => ['attributes' => [
                        ['key' => 'annee', 'label' => 'Année', 'type' => AttributeType::Number, 'min' => 1900, 'max' => 2100, 'required' => true],
                        ['key' => 'kilometrage', 'label' => 'Kilométrage', 'type' => AttributeType::Number, 'unit' => 'km', 'min' => 0, 'max' => 1000000],
                        ['key' => 'cylindree', 'label' => 'Cylindrée', 'type' => AttributeType::Number, 'unit' => 'cm³', 'min' => 50, 'max' => 3000],
                    ]],
                    'Équipement auto' => ['attributes' => [self::ETAT]],
                ],
            ],
            'Mode' => [
                'attributes' => [self::ETAT],
                'children' => [
                    'Vêtements' => ['attributes' => [
                        ['key' => 'taille', 'label' => 'Taille', 'type' => AttributeType::Select, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                        ['key' => 'univers', 'label' => 'Univers', 'type' => AttributeType::Select, 'options' => ['femme', 'homme', 'enfant']],
                    ]],
                    'Chaussures' => ['attributes' => [
                        ['key' => 'pointure', 'label' => 'Pointure', 'type' => AttributeType::Number, 'min' => 15, 'max' => 50],
                    ]],
                    'Accessoires & bagagerie' => [],
                ],
            ],
            'Maison & Jardin' => [
                'attributes' => [self::ETAT],
                'children' => ['Ameublement' => [], 'Électroménager' => [], 'Jardinage' => [], 'Bricolage' => []],
            ],
            'Multimédia' => [
                'attributes' => [self::ETAT],
                'children' => [
                    'Téléphones' => ['attributes' => [
                        ['key' => 'stockage', 'label' => 'Stockage', 'type' => AttributeType::Select, 'options' => ['32', '64', '128', '256', '512', '1024'], 'unit' => 'Go'],
                    ]],
                    'Informatique' => [], 'Consoles & jeux vidéo' => [], 'Image & son' => [],
                ],
            ],
            'Loisirs' => [
                'attributes' => [self::ETAT],
                'children' => ['Livres' => [], 'Sports & hobbies' => [], 'Jeux & jouets' => [], 'Instruments de musique' => []],
            ],
            'Emploi' => [
                'children' => ['Offres d\'emploi' => ['attributes' => [
                    ['key' => 'contrat', 'label' => 'Type de contrat', 'type' => AttributeType::Select, 'required' => true,
                        'options' => ['cdi', 'cdd', 'interim', 'alternance', 'stage', 'independant']],
                    ['key' => 'teletravail', 'label' => 'Télétravail possible', 'type' => AttributeType::Boolean],
                ]]],
            ],
            'Services' => [
                'children' => ['Cours particuliers' => [], 'Services à la personne' => [], 'Déménagement' => []],
            ],
            'Matériel professionnel' => [
                'attributes' => [self::ETAT],
                'children' => ['BTP & chantier' => [], 'Restauration' => [], 'Équipements industriels' => []],
            ],
        ];

        $this->seedLevel($tree, null);
    }

    /** @param  array<string, array<string, mixed>>  $nodes */
    private function seedLevel(array $nodes, ?Category $parent): void
    {
        $position = 0;

        foreach ($nodes as $name => $node) {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'parent_id' => $parent?->id, 'position' => $position++],
            );

            foreach ($node['attributes'] ?? [] as $index => $attribute) {
                $category->attributeDefinitions()->updateOrCreate(
                    ['key' => $attribute['key']],
                    [
                        'label' => $attribute['label'],
                        'type' => $attribute['type'],
                        'unit' => $attribute['unit'] ?? null,
                        'options' => $attribute['options'] ?? [],
                        'min_value' => $attribute['min'] ?? null,
                        'max_value' => $attribute['max'] ?? null,
                        'is_required' => $attribute['required'] ?? false,
                        'position' => $index,
                    ],
                );
            }

            $this->seedLevel($node['children'] ?? [], $category);
        }
    }
}
