<?php

/*
| Photos d'annonces (DEP-04, cahier des charges §5.5).
*/

return [

    // 10 photos incluses ; jusqu'à 30 avec l'option payante (DEP-07, plus tard).
    'max_per_listing' => (int) env('PHOTOS_MAX_PER_LISTING', 10),

    // 15 Mo maximum par photo.
    'max_bytes' => 15 * 1024 * 1024,

    // Formats acceptés à l'envoi (le HEIC est converti en JPEG par le navigateur avant envoi).
    'mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    // Garde-fous contre les « bombes de décompression ».
    'max_pixels' => 50_000_000,
    'min_side' => 200,

    // Déclinaisons : vignette, liste, fiche, plein écran (largeur maximale en pixels).
    'sizes' => [
        'thumb' => 200,
        'card' => 400,
        'detail' => 1200,
        'full' => 2000,
    ],

    'quality' => [
        'webp' => 80,
        'avif' => 55,
    ],

    // Durée de validité du formulaire d'envoi signé.
    'upload_url_ttl_minutes' => 15,

    // Origines du frontend autorisées à envoyer directement au stockage (CORS du bucket des originaux).
    'cors_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'PHOTOS_CORS_ORIGINS',
        'http://localhost:4200,http://localhost:4201',
    ))))),

    // Disques (config/filesystems.php).
    'uploads_disk' => 'uploads',
    'public_disk' => 'photos',
];
