<?php

/*
| Recherche (RECH-01 à 07) — OpenSearch / Elasticsearch (API REST compatible).
*/

return [

    'host' => env('SEARCH_HOST', 'http://localhost:9200'),
    'username' => env('SEARCH_USERNAME'),
    'password' => env('SEARCH_PASSWORD'),
    'timeout' => (float) env('SEARCH_TIMEOUT', 5),

    // Préfixe des index (ex. « test_ » pour PHPUnit, « recette_ »…).
    'prefix' => env('SEARCH_INDEX_PREFIX', ''),

    // Synchronisation automatique des annonces vers l'index (désactivée dans la plupart des tests).
    'sync' => (bool) env('SEARCH_SYNC', true),

    // Pagination : au-delà, utiliser la recherche par zone / affiner les filtres (anti-scraping §6.2).
    'per_page' => 24,
    'max_per_page' => 48,
    'max_page' => 100,

    // Rayons proposés (RECH-04), en km.
    'radii' => [5, 10, 20, 50, 100, 200],

    // Synonymes appliqués à la recherche (format Solr). À enrichir depuis le back-office plus tard.
    'synonyms' => [
        'velo, bicyclette, vtt, vtc',
        'voiture, auto, automobile, vehicule',
        'appartement, appart, logement, studio',
        'maison, pavillon, villa',
        'canape, sofa, divan',
        'television, televiseur, tv, tele',
        'telephone, smartphone, portable, mobile',
        'ordinateur, pc, laptop',
        'frigo, refrigerateur',
        'poussette, landau',
        'moto, scooter, deux roues',
    ],
];
