<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        // Données de démonstration : uniquement en local (jamais en recette ni en production).
        if (app()->environment('local')) {
            $this->call(DemoListingsSeeder::class);
        }

        User::query()->firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
        ]);
    }
}
