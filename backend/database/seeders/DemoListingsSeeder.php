<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Modules\Catalog\Services\CategorySchema;
use App\Support\Geo\GeoPoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Annonces de démonstration (environnement local uniquement) : de quoi tester la recherche
 * tant que la modération (BO-01) ne publie pas encore d'annonces. Idempotent.
 */
class DemoListingsSeeder extends Seeder
{
    private const SELLER_DOMAIN = '@demo.local';

    /** Bornes réalistes pour les attributs numériques (le schéma autorise plus large). */
    private const REALISTIC = [
        'kilometrage' => [5000, 220000], 'annee' => [2008, 2024], 'surface' => [18, 220], 'pieces' => [1, 7],
        'cylindree' => [125, 1000], 'pointure' => [35, 46],
    ];

    private const CITIES = [
        'Paris' => [48.8566, 2.3522], 'Lyon' => [45.7640, 4.8357], 'Marseille' => [43.2965, 5.3698],
        'Toulouse' => [43.6047, 1.4442], 'Bordeaux' => [44.8378, -0.5792], 'Lille' => [50.6292, 3.0573],
        'Nantes' => [47.2184, -1.5536], 'Strasbourg' => [48.5734, 7.7521], 'Rennes' => [48.1173, -1.6778],
        'Montpellier' => [43.6108, 3.8767],
    ];

    /** slug de catégorie => [titres, fourchette de prix] */
    private const CATALOGUE = [
        'voitures' => [['Peugeot 208 1.2 PureTech', 'Renault Clio IV dCi', 'Volkswagen Golf 7 TDI', 'Citroën C3 très bon état', 'Toyota Yaris hybride', 'Dacia Sandero Stepway', 'Tesla Model 3 Grande Autonomie', 'Fiat 500 cabriolet'], [2500, 32000]],
        'motos' => [['Yamaha MT-07', 'Honda CB500F', 'Scooter Piaggio Vespa 125', 'Kawasaki Z650'], [1500, 8000]],
        'ventes-immobilieres' => [['Appartement T3 lumineux avec balcon', 'Maison 4 pièces avec jardin', 'Studio proche gare', 'Loft rénové centre-ville'], [89000, 520000]],
        'locations' => [['T2 meublé proche tram', 'Studio étudiant', 'Maison 3 chambres avec garage'], [450, 1600]],
        'vetements' => [['Veste en jean Levi’s', 'Manteau en laine taille M', 'Robe d’été fleurie', 'Sweat à capuche Nike', 'Jean slim Diesel'], [8, 120]],
        'chaussures' => [['Baskets Nike Air Max', 'Bottes en cuir', 'Chaussures de randonnée Salomon'], [15, 110]],
        'ameublement' => [['Canapé d’angle convertible', 'Table en chêne massif', 'Commode scandinave', 'Fauteuil velours vintage', 'Bibliothèque blanche'], [30, 650]],
        'electromenager' => [['Lave-linge Bosch 8 kg', 'Réfrigérateur américain Samsung', 'Four micro-ondes', 'Aspirateur Dyson V11'], [40, 900]],
        'telephones' => [['iPhone 13 128 Go', 'Samsung Galaxy S22', 'Google Pixel 7', 'iPhone 12 Mini'], [150, 750]],
        'informatique' => [['MacBook Air M1', 'PC portable Lenovo ThinkPad', 'Écran 27 pouces Dell', 'Clavier mécanique Logitech'], [40, 1100]],
        'consoles-jeux-video' => [['PlayStation 5 avec 2 manettes', 'Nintendo Switch OLED', 'Xbox Series S', 'Lot de jeux PS4'], [25, 450]],
        'sports-hobbies' => [['Vélo de ville électrique', 'VTT Rockrider 27,5"', 'Planche de surf 7\'2', 'Tapis de course pliable', 'Raquettes de tennis Babolat'], [20, 1400]],
        'jeux-jouets' => [['Poussette Yoyo Babyzen', 'Lego Technic Porsche', 'Trottinette enfant', 'Cuisine en bois'], [10, 350]],
        'livres' => [['Lot de romans policiers', 'Intégrale Harry Potter', 'BD Astérix collection'], [5, 60]],
    ];

    public function run(CategorySchema $schemas): void
    {
        if (Listing::query()->whereHas('user', fn ($q) => $q->where('email', 'like', '%'.self::SELLER_DOMAIN))->exists()) {
            $this->command?->info('Annonces de démo déjà présentes.');

            return;
        }

        $sellers = collect(range(1, 8))->map(fn (int $i) => User::query()->firstOrCreate(
            ['email' => 'vendeur'.$i.self::SELLER_DOMAIN],
            ['name' => fake('fr_FR')->firstName().' '.fake('fr_FR')->lastName()[0].'.', 'password' => Str::password(20)],
        ));
        $faker = fake('fr_FR');
        $count = 0;

        foreach (self::CATALOGUE as $slug => [$titles, [$minPrice, $maxPrice]]) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category === null) {
                continue;
            }
            $schema = $schemas->for($category);

            foreach ($titles as $title) {
                foreach (range(1, 2) as $_) {
                    [$city, [$lat, $lng]] = [$name = array_rand(self::CITIES), self::CITIES[$name]];
                    $publishedAt = now()->subMinutes(random_int(10, 60 * 24 * 30));

                    $attributes = [];
                    foreach ($schema as $key => $definition) {
                        $attributes[$key] = match ($definition->type) {
                            AttributeType::Number => $faker->numberBetween(...(self::REALISTIC[$key] ?? [(int) ($definition->min_value ?? 0), (int) ($definition->max_value ?? 100)])),
                            AttributeType::Select => $faker->randomElement($definition->options),
                            AttributeType::MultiSelect => $faker->randomElements($definition->options, 2),
                            AttributeType::Boolean => $faker->boolean(),
                            AttributeType::Text => $key === 'marque' ? Str::before($title, ' ') : $faker->word(),
                        };
                    }
                    Listing::query()->create([
                        'user_id' => $sellers->random()->id,
                        'category_id' => $category->id,
                        'type' => in_array($slug, ['locations'], true) ? ListingType::Rental : ListingType::Sale,
                        'title' => $title,
                        'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
                        'description' => "{$title} à {$city}. ".$faker->sentence(14).' Remise en main propre possible.',
                        'price' => $this->roundPrice($faker->numberBetween($minPrice, $maxPrice)),
                        'status' => ListingStatus::Active,
                        'attribute_values' => $attributes,
                        // Dispersion d'environ 10 km autour du centre-ville.
                        'location' => new GeoPoint($lat + $faker->randomFloat(4, -0.09, 0.09), $lng + $faker->randomFloat(4, -0.12, 0.12)),
                        'published_at' => $publishedAt,
                        'expires_at' => $publishedAt->copy()->addDays(Listing::LIFETIME_DAYS),
                    ]);
                    $count++;
                }
            }
        }

        $this->command?->info("{$count} annonces de démo créées. Pensez à : php artisan search:reindex");
    }

    private function roundPrice(int $price): int
    {
        return match (true) {
            $price >= 10000 => (int) (round($price / 500) * 500),
            $price >= 1000 => (int) (round($price / 50) * 50),
            $price >= 100 => (int) (round($price / 5) * 5),
            default => $price,
        };
    }
}
