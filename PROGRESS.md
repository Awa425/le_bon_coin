# Journal de progression

## Initialisation

- **Date et heure :** 2026-11-17 02:26 (Europe/Paris)
- **Module / User Story :** INIT-01 — Initialisation du projet, socle et base de données
- **Statut :** Socles, modèles et tests réalisés. PHPUnit : 6/6 tests réussis (82 assertions) ; Angular : 2/2 tests réussis ; build SSR et Pint réussis. La migration PostGIS n'a pas pu être exécutée : Docker Engine est indisponible et le service PostgreSQL local est arrêté.
- **Fichiers créés ou modifiés :**
  - Racine : `PROGRESS.md`, `README.md`, `docker-compose.yml`.
  - Socles générés : projet Laravel dans `backend/` et application Angular standalone/SSR dans `frontend/`.
  - Configuration : `backend/.env.example` (PostgreSQL/PostGIS) et `backend/.env` local (ignoré par Git).
  - Migrations : `backend/database/migrations/0001_01_01_000000_create_users_table.php` et `2026_11_17_000001_create_categories_table.php` à `2026_11_17_000006_create_messages_table.php`.
  - Modèles : `backend/app/Models/User.php`, `Category.php`, `AttributeDefinition.php`, `Listing.php`, `Photo.php`, `Conversation.php`, `Message.php`.
  - Enums : `backend/app/Enums/AttributeType.php`, `ListingStatus.php`.
  - Tests : `backend/tests/Unit/MarketplaceModelsTest.php` ; tests générés du socle Laravel et Angular.

## Revue et corrections du socle

- **Date et heure :** 2026-11-17 02:41 (Europe/Paris)
- **Module / User Story :** INIT-01 (correctifs) — Vérification des migrations sur PostgreSQL/PostGIS réel
- **Statut :** Terminé / Testé avec succès. PHPUnit : 27/27 tests (134 assertions) sur PostGIS 16-3.5 ; Pint OK ; Angular : 2/2 tests, build SSR OK.
- **Problèmes corrigés :**
  - Migration `categories` bloquante sur PostgreSQL (FK auto-référente créée avant la clé primaire) : les migrations n'avaient jamais été exécutées.
  - Tests exécutés sur SQLite, incompatible JSONB/PostGIS : PHPUnit pointe désormais sur la base PostGIS `marketplace_testing`.
  - Colonne `listings.attributes` renommée `attribute_values` (collision avec la propriété interne `$attributes` d'Eloquent).
  - Colonne `location` sans cast : ajout du value object `GeoPoint` et du cast `GeoPointCast` (écriture EWKT, lecture EWKB).
  - Index manquants sur les clés étrangères (PostgreSQL ne les crée pas automatiquement) et index GIN sur `attribute_values`.
  - Contraintes CHECK ajoutées : prix ≥ 0, statut d'annonce valide, acheteur ≠ vendeur.
  - Extension PostGIS activée dans une migration dédiée.
  - Espace parasite dans `DB_DATABASE` du `.env`.
  - SSR Angular : `RenderMode.Prerender` sur `**` remplacé par `RenderMode.Server` (pages dynamiques).
- **Fichiers créés ou modifiés :**
  - Migrations : `2026_11_17_000000_enable_postgis_extension.php` (nouveau), `000001_create_categories_table.php`, `000003_create_listings_table.php`, `000005_create_conversations_table.php`, `000006_create_messages_table.php`.
  - Code : `backend/app/Support/Geo/GeoPoint.php`, `backend/app/Casts/GeoPointCast.php` (nouveaux) ; tous les modèles (`HasFactory`), `Listing.php` (casts).
  - Factories : `CategoryFactory`, `AttributeDefinitionFactory`, `ListingFactory`, `PhotoFactory`, `ConversationFactory`, `MessageFactory` (nouvelles), `UserFactory` (téléphone).
  - Tests : `backend/tests/Unit/GeoPointTest.php`, `backend/tests/Feature/DatabaseSchemaTest.php` (nouveaux), `backend/tests/Unit/MarketplaceModelsTest.php`.
  - Configuration : `backend/phpunit.xml`, `backend/.env`, `docker-compose.yml`, `docker/postgres/init/01-create-testing-database.sql` (nouveau), `README.md`.
  - Frontend : `frontend/src/app/app.routes.server.ts`.

## Correctif SSR Angular

- **Date et heure :** 2026-11-17 08:27 (Europe/Paris)
- **Module / User Story :** INIT-01 (correctif) — Serveur SSR Angular
- **Statut :** Terminé / Testé avec succès. Serveur SSR lancé et interrogé : `GET /` → 200 avec rendu serveur (`ng-server-context="ssr"`), hôte non autorisé → 400. Angular : 2/2 tests ; PHPUnit : 27/27.
- **Problème :** le passage en `RenderMode.Server` faisait répondre 400 à toutes les requêtes (protection SSRF d'Angular : aucun hôte autorisé). Corrigé en déclarant `allowedHosts` dans `AngularNodeAppEngine` ; en production, ajouter le domaine via `NG_ALLOWED_HOSTS`.
- **Fichiers modifiés :** `frontend/src/server.ts`.

## Alignement de l'environnement local

- **Date et heure :** 2026-11-17 08:31 (Europe/Paris)
- **Module / User Story :** INIT-01 (configuration) — Base de développement
- **Statut :** Terminé / Testé avec succès. `php artisan migrate:fresh` exécute les 10 migrations sur le conteneur PostGIS ; création et relecture d'une annonce (JSONB + géolocalisation) vérifiées, puis données de test supprimées.
- **Fichiers modifiés :** `backend/.env` (identifiants alignés sur `docker-compose.yml` : base, utilisateur et mot de passe `marketplace` / `marketplace_local`).

## Étape 2 — Comptes, catalogue et dépôt d'annonce (Lot 1)

- **Date et heure :** 2026-11-17 08:52 (Europe/Paris)
- **Modules / User Stories :** Identité & comptes (socle SEC), Catalogue (RECH-02 arborescence, RECH-03 / BO-05 schéma d'attributs), DEP-01 (formulaire adapté à la catégorie), DEP-06 (téléphone masqué par défaut)
- **Statut :** Terminé / Testé avec succès. PHPUnit : 66/66 tests (270 assertions) sur PostGIS ; Pint OK. Parcours HTTP réel vérifié sur `php artisan serve` : inscription → schéma « Voitures » → brouillon → soumission refusée (champs manquants) → complétion → soumission (`pending_moderation`) → accès anonyme refusé (403).
- **Réalisé :**
  - API REST versionnée `/api/v1` (10 routes), modules `app/Modules/{Identity,Catalog,Listings}`.
  - Auth par jeton Sanctum : inscription, connexion, déconnexion, profil ; Argon2id ; anti-énumération ; rate-limiting (connexion 5/min par e-mail+IP, API 60/min anonyme, 5 annonces/heure).
  - Catalogue : arbre limité à 3 niveaux (cycles interdits), héritage des attributs parents, dépôt uniquement en catégorie finale ; seeder idempotent (9 catégories racines, 37 catégories, 24 attributs).
  - DEP-01 : règles de validation générées depuis le schéma (types, bornes, listes, clés inconnues refusées), brouillon à sauvegarde progressive, soumission avec contrôle des obligatoires.
  - Statuts d'annonce alignés sur le cahier des charges (`pending_moderation`, `refused`, `expired` ajoutés ; `archived` retiré) avec transitions contrôlées ; type d'annonce (vente, location, recherche, service, emploi).
- **Fichiers créés ou modifiés :**
  - Modules : `backend/app/Modules/Identity/Http/{Controllers/AuthController,Requests/RegisterRequest,Requests/LoginRequest,Resources/UserResource}.php` ; `backend/app/Modules/Catalog/{Services/CategorySchema,Validation/AttributeRules,Http/Controllers/CategoryController,Http/Resources/CategoryResource,Http/Resources/AttributeDefinitionResource}.php` ; `backend/app/Modules/Listings/{Actions/SaveListingDraft,Actions/SubmitListing,Policies/ListingPolicy,Http/Controllers/ListingController,Http/Requests/ListingDraftRequest,Http/Resources/ListingResource}.php`.
  - Domaine : `backend/app/Enums/{ListingStatus,ListingType,AttributeType}.php`, `backend/app/Models/{User,Category,AttributeDefinition,Listing}.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/config/sanctum.php`.
  - Base : migrations `000002_create_attribute_definitions_table` et `000003_create_listings_table` modifiées (pas encore de production), `2026_11_17_074032_create_personal_access_tokens_table.php` (UUID) ; `database/seeders/{CategorySeeder,DatabaseSeeder}.php` ; factories `ListingFactory`, `AttributeDefinitionFactory`.
  - Configuration : `backend/.env`, `backend/.env.example` (Argon2id, locale fr), `backend/phpunit.xml`, `backend/composer.json` (laravel/sanctum).
  - Tests : `backend/tests/Unit/{ListingStatusTest,AttributeRulesTest,MarketplaceModelsTest}.php`, `backend/tests/Feature/Identity/AuthApiTest.php`, `backend/tests/Feature/Catalog/CategoryApiTest.php`, `backend/tests/Feature/Listings/ListingDraftApiTest.php`.
- **Reste à faire identifié :** messages de validation en anglais (traductions FR à ajouter), vérification e-mail/SMS (SEC-01), mode cookie SPA Sanctum lors du branchement Angular, modération automatique (BO-01).

## Traductions françaises de l'API

- **Date et heure :** 2026-11-17 09:21 (Europe/Paris)
- **Module :** Transverse — i18n (cahier des charges §6.5 : contenu français au lancement)
- **Statut :** Terminé / Testé avec succès. PHPUnit : 71/71 (dont `FrenchMessagesTest`).
- **Réalisé :** paquet communautaire `laravel-lang/common` (dev) → `lang/fr/*.php` et `lang/fr.json` générés ; locale `fr` (`.env`, `.env.example`, `phpunit.xml`) ; libellés français des champs d'annonce (`ListingFieldNames`) gardés hors des fichiers régénérés ; libellés français des statuts (`ListingStatus::label()`).
- **Fichiers créés ou modifiés :** `backend/lang/fr/*`, `backend/lang/fr.json`, `backend/app/Modules/Listings/ListingFieldNames.php`, `backend/app/Enums/ListingStatus.php`, `backend/app/Modules/Listings/Http/Requests/ListingDraftRequest.php`, `backend/app/Modules/Listings/Actions/SubmitListing.php`, `backend/composer.json`, `backend/phpunit.xml`, `backend/tests/Feature/FrenchMessagesTest.php`.

## Étape 3 — Frontend Angular : comptes et tunnel de dépôt (DEP-01)

- **Date et heure :** 2026-11-17 09:21 (Europe/Paris)
- **Modules / User Stories :** DEP-01 (formulaire adapté à la catégorie), DEP-06 (téléphone masqué), comptes (connexion / inscription), accueil SSR
- **Statut :** Terminé / Testé avec succès. Vitest : 40/40 tests (8 fichiers) ; build SSR OK sans avertissement. Vérification réelle (API + serveur SSR lancés) : accueil rendu côté serveur avec les 9 catégories de l'API, `/deposer` en rendu client, CORS validé pour les appels authentifiés.
- **Réalisé :**
  - `core/` : `API_BASE_URL`, conversion des erreurs Laravel (422 → erreurs par champ, messages français par statut), `AuthService` à base de signaux, intercepteur (jeton uniquement vers notre API, fermeture de session sur 401), garde de route avec retour à la page demandée et protection contre les redirections ouvertes.
  - Tunnel de dépôt en 4 étapes avec sauvegarde du brouillon à chaque étape : sélecteur de catégorie en cascade (catégorie finale obligatoire), champs de caractéristiques générés depuis le schéma (texte, nombre avec bornes, liste, multi-choix, booléen) avec les mêmes règles que l'API, prix obligatoire selon le type, localisation (« Utiliser ma position » ou saisie), soumission en modération ; une erreur serveur renvoie à l'étape concernée.
  - Rendu : SSR pour l'accueil, rendu client pour connexion / inscription / dépôt (§5.2). Accessibilité : lien d'évitement, libellés associés, `aria-invalid`, cibles tactiles ≥ 44 px, `lang="fr"`.
  - Bug corrigé grâce aux tests : l'erreur « prix obligatoire » restait bloquante après passage d'une vente à une recherche.
- **Fichiers créés ou modifiés :**
  - `frontend/src/app/core/api/{api-config,api-errors}.ts`, `frontend/src/app/core/auth/{auth.models,auth.service,auth.interceptor,auth.guard,token-storage}.ts`
  - `frontend/src/app/catalog/{category.models,category.service}.ts`, `frontend/src/app/listings/{listing.models,listing.service}.ts`
  - `frontend/src/app/features/deposit/{deposit.page.ts,deposit.page.html,category-picker.ts,attribute-fields.ts,attribute-form.ts}`, `frontend/src/app/features/auth/{login.page,register.page}.ts`, `frontend/src/app/features/home/home.page.ts`, `frontend/src/app/layout/site-header.ts`
  - `frontend/src/app/{app.ts,app.config.ts,app.routes.ts,app.routes.server.ts}`, `frontend/src/index.html`, `frontend/src/styles.scss` ; supprimés : `app.html`, `app.scss` (gabarit d'exemple)
  - Tests : `frontend/src/app/app.spec.ts`, `core/api/api-errors.spec.ts`, `core/auth/auth.spec.ts`, `features/deposit/{attribute-form,attribute-fields,category-picker,deposit.page}.spec.ts`, `features/auth/auth-pages.spec.ts`, utilitaires `frontend/src/testing/dom.ts`
- **Reste à faire identifié :** jeton stocké en `localStorage` (à remplacer par le cookie HttpOnly de Sanctum avant production) ; géocodage ville / code postal (RECH-04) à la place de la saisie de coordonnées ; étape Photos (DEP-04) ; tests E2E navigateur (Playwright) non encore en place.

## Refonte graphique — charte blanc & orange « la Plateforme »

- **Date et heure :** 2026-11-17 14:23 (Europe/Paris)
- **Module :** Transverse — design system et UI (d'après la maquette fournie)
- **Statut :** Terminé / Testé avec succès. Vitest : 53/53 (11 fichiers) ; build sans avertissement (379 Ko initial, 102 Ko compressé). Vérification visuelle par captures Chrome (CDP) en 1440 px et en émulation mobile 390 px : 0 px de débordement horizontal sur l'accueil et le tunnel de dépôt.
- **Réalisé :**
  - Design system global : palette orange / sable / encre, contrastes WCAG AA (orange vif réservé aux grands textes et décors, boutons en `#cc4a0b` 4,6:1), polices auto-hébergées Bricolage Grotesque (titres) et Figtree (texte), boutons pilule, champs 50 px, pastilles de choix, focus visibles, `prefers-reduced-motion`.
  - En-tête : logo provisoire « la Plateforme », recherche, Favoris / Messages / Mon compte, bouton « Déposer une annonce » (version compacte sur mobile).
  - Accueil : hero dégradé pêche, recherche Quoi / Où / Rayon (rayons RECH-04), recherches populaires, tuiles catégories avec icônes, promesses, bandeau d'appel, pied de page.
  - Connexion / inscription : mise en page deux colonnes avec panneau de marque.
  - Tunnel de dépôt : étapes à pastilles reliées, choix en pastilles, champ prix, interrupteur « masquer mon numéro », encart de conseils par étape, écran de succès.
  - Nouvelles pages : Mon compte (profil + déconnexion), pages « bientôt disponible » pour Recherche, Favoris et Messages.
  - Défauts corrigés grâce aux captures : en-tête trop large sur mobile, bordure native du `fieldset`, césure de « Matériel professionnel ».
- **Fichiers créés ou modifiés :**
  - `frontend/src/styles.scss`, `frontend/angular.json` (polices, budgets), `frontend/package.json` (`@fontsource-variable/figtree`, `@fontsource-variable/bricolage-grotesque`)
  - `frontend/src/app/ui/{icon,logo}.ts`, `frontend/src/app/layout/{site-header.ts,site-header.scss,site-footer.ts}`, `frontend/src/app/app.ts`, `frontend/src/app/app.routes.ts`, `frontend/src/app/app.routes.server.ts`
  - `frontend/src/app/features/home/home.page.{ts,html,scss}`, `features/auth/{auth-shell,login.page,register.page}.ts`, `features/deposit/{deposit.page.ts,deposit.page.html,deposit.page.scss,attribute-fields.ts,category-picker.ts}`, `features/account/account.page.ts`, `features/coming-soon/coming-soon.page.ts`
  - Tests : `app.spec.ts`, `features/home/home.page.spec.ts`, `features/pages.spec.ts`, `ui/ui.spec.ts`

## Étape 4 — Photos d'annonce (DEP-04)

- **Date et heure :** 2026-11-17 15:20 (Europe/Paris)
- **Module / User Story :** DEP-04 — jusqu'à 10 photos, recadrage, réordonnancement, photo principale, envoi direct au stockage objet, suppression EXIF
- **Statut :** Code terminé, testé avec succès en automatique — PHPUnit 92/92 (378 assertions), Vitest 79/79 (15 fichiers), build sans avertissement. **Vérification de bout en bout avec le vrai stockage S3 en attente** : l'installation de `aws/aws-sdk-php` est bloquée par un verrou de fichiers Windows (serveur de langage de l'extension VS Code PHP IntelliSense qui indexe `vendor/`).
- **Réalisé :**
  - Stockage S3 local SeaweedFS 3.97 (MinIO n'est plus distribué publiquement) : bucket privé `marketplace-uploads` (originaux) et bucket public `marketplace-photos` (déclinaisons) ; commande `php artisan photos:setup-storage`.
  - API : réservation + URL PUT pré-signée (type et taille signés, 15 min), confirmation, suivi, réordonnancement, suppression ; quota de 10 photos avec verrou anti-concurrence ; 60 envois/heure ; photos rattachées à leur annonce (scoped bindings).
  - Traitement asynchrone (`ProcessPhoto`, 3 tentatives) : contrôle du format réel, garde-fou 50 Mpx, redressement EXIF, déclinaisons WebP + AVIF 200/400/1200/2000 px sans agrandissement, cache immuable 1 an, suppression de l'original (EXIF/GPS) ; fichier piégé → photo en échec avec motif.
  - Soumission : au moins une photo prête pour une vente ou une location.
  - Frontend : étape « Photos » insérée en 2ᵉ position (tunnel en 5 étapes, §3.B) ; préparation côté navigateur (orientation, réduction 2 400 px, ré-encodage JPEG sans métadonnées) ; envoi direct sans jeton vers le stockage ; suivi séquentiel du traitement ; vignettes, badge « Principale », glisser-déposer + boutons clavier, recadrage (formats, zoom, déplacement souris / tactile / clavier) ; état conservé lors des allers-retours entre étapes.
  - Défauts corrigés grâce aux tests : interrogation du traitement qui empilait des requêtes, gestionnaire de photos réinitialisé en revenant à l'étape.
- **Fichiers créés ou modifiés :**
  - Infra : `docker-compose.yml` (service `s3`), `docker/seaweedfs/s3.json`, `backend/config/filesystems.php` (disques `uploads`, `photos`), `backend/config/photos.php`, `backend/.env`, `backend/.env.example`, `backend/composer.json` (`league/flysystem-aws-s3-v3`), `backend/bootstrap/app.php` (commandes des modules).
  - Backend : `app/Enums/PhotoStatus.php`, `app/Models/Photo.php`, migration `2026_11_17_000004_create_photos_table.php`, `app/Modules/Media/{Images/ImageProcessor,Images/ProcessedImage,Images/ImageVariant,Images/InvalidImage,Jobs/ProcessPhoto,Http/Controllers/PhotoController,Http/Resources/PhotoResource,Console/SetupPhotoStorage}.php`, `app/Modules/Listings/{Actions/SubmitListing,Http/Controllers/ListingController,Http/Resources/ListingResource,ListingFieldNames}.php`, `routes/api.php`, `app/Providers/AppServiceProvider.php`, `database/factories/PhotoFactory.php`.
  - Tests backend : `tests/Unit/ImageProcessorTest.php`, `tests/Feature/Media/PhotoApiTest.php`, `tests/Support/Images.php`, `tests/TestCase.php` (stockage toujours simulé), `tests/Feature/Listings/ListingDraftApiTest.php`.
  - Frontend : `src/app/listings/{listing.models,photo.service}.ts`, `src/app/features/deposit/photos/{photo-manager.ts,photo-manager.html,photo-manager.scss,crop-editor.ts,crop-editor.scss,crop-geometry.ts,image-preparer.ts}`, `src/app/features/deposit/{deposit.page.ts,deposit.page.html,deposit.page.scss}`, `src/app/ui/icon.ts`.
  - Tests frontend : `listings/photo.service.spec.ts`, `features/deposit/photos/{photo-manager,crop-editor,crop-geometry}.spec.ts`, `features/deposit/deposit.page.spec.ts`.
- **Reste à faire identifié :** antivirus et détection d'images inappropriées (BO-01) ; nettoyage planifié des emplacements jamais confirmés ; option 30 photos (DEP-07) ; texte alternatif saisi par le vendeur.

## DEP-04 — Vérification de bout en bout et correctifs de sécurité

- **Date et heure :** 2026-11-18 (Europe/Paris)
- **Module / User Story :** DEP-04 (suite)
- **Statut :** Terminé / Testé avec succès. PHPUnit 93/93 (391 assertions), Vitest 79/79. Parcours réel API + stockage S3 + worker : 25 vérifications conformes (traitement en ~5 s, orientation EXIF appliquée, 8 déclinaisons WebP/AVIF publiques sans métadonnées, original privé puis supprimé, bucket privé vide après traitement, suppression des déclinaisons). **Non vérifié** : le parcours de l'écran Photos dans un vrai navigateur (script de captures bloqué, machine à 1,8 Go de mémoire libre) — couvert par les tests de composants.
- **Problèmes trouvés par le parcours réel et corrigés :**
  - CORS : le bucket refusait la requête préalable du navigateur → CORS configuré au niveau du bucket des originaux (`PutBucketCors`, origines via `PHOTOS_CORS_ORIGINS`), comme il faudra le faire chez l'hébergeur.
  - Taille non imposée : une URL PUT pré-signée ne signe pas `Content-Length` → passage à un **formulaire POST signé** (politique S3) qui impose au stockage la clé, le type et la taille exacts. Vérifié : fichier plus gros refusé (`EntityTooLarge`), type ou emplacement modifiés refusés et non stockés (SeaweedFS répond 307 là où AWS répond 403).
  - Installation du SDK AWS bloquée par un verrou de fichiers de l'extension VS Code PHP IntelliSense : installation réalisée dans un dossier temporaire puis recopiée.
- **Fichiers créés ou modifiés :** `backend/app/Modules/Media/Uploads/{PhotoUploadSigner,S3PostPolicySigner,UploadForm}.php` (nouveaux), `backend/app/Modules/Media/Http/Controllers/PhotoController.php`, `backend/app/Modules/Media/Console/SetupPhotoStorage.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/config/photos.php`, `backend/.env`, `backend/.env.example`, `backend/tests/Support/FakeUploadSigner.php`, `backend/tests/Unit/S3PostPolicySignerTest.php`, `backend/tests/Feature/Media/PhotoApiTest.php`, `frontend/src/app/listings/{listing.models,photo.service}.ts`, `frontend/src/app/listings/photo.service.spec.ts`, `frontend/src/app/features/deposit/photos/photo-manager.spec.ts`.
- **Pour lancer en local :** `docker compose up -d postgres s3`, `php artisan photos:setup-storage`, `php artisan serve`, `php artisan queue:work`, `npm start`.

## Étape 5 — Recherche, API (RECH-01 à 04, 06, 07)

- **Date et heure :** 2026-11-18 (Europe/Paris)
- **Modules / User Stories :** RECH-01 (mots-clés tolérants + autocomplétion), RECH-02 (catégories + compteurs), RECH-03 (filtres d'attributs générés depuis le schéma), RECH-04 (rayon autour d'un point), RECH-06 (tris), RECH-07 (filtres particulier/pro, livraison, urgent — champs prêts, alimentés par PRO-01 / DEP-05 / DEP-07)
- **Statut :** Terminé / Testé avec succès (backend). PHPUnit 110/110 (2 passages complets stables), dont 17 tests de recherche contre un vrai OpenSearch 2.19.1 (index « test_ »). Vérifié sur 120 annonces de démo : « peugot » → Peugeot, « bicyclette » → vélos/VTT, « canapes » → canapés, filtres + tris, rayon de 20 km autour de Lyon, suggestions « ipho » → iPhone ; ~190 ms en médiane via `php artisan serve` (cible < 200 ms).
- **Réalisé :**
  - OpenSearch 2.19.1 dans `docker-compose.yml` (nœud unique, 512 Mo de tas, ~1 Go au total).
  - Client REST sans dépendance (`Http` de Laravel) ; erreurs et indisponibilité → 503 explicite.
  - Index versionné derrière un alias, réindexation sans interruption (`php artisan search:reindex`) ; mapping strict ; analyse française (élisions, accents ignorés, mots vides, racinisation « light_french », synonymes à la recherche) ; tolérance aux fautes `AUTO` ; autocomplétion par n-grammes ; attributs rangés par type (`kw` / `num` / `bool`) pour éviter les conflits de mapping.
  - Synchronisation asynchrone base → index à chaque changement d'annonce ou de photo (après validation de la transaction, tâche unique par annonce, 5 tentatives) ; seules les annonces actives ou réservées sont indexées.
  - API `GET /api/v1/search` (paramètres en français, conservés dans l'URL) et `GET /api/v1/search/suggest` ; distance arrondie, jamais la position exacte ; cache CDN 60 s.
  - Seeder `DemoListingsSeeder` (120 annonces réalistes sur 14 catégories et 10 villes, local uniquement).
  - Défauts trouvés par les tests et corrigés : erreur de connexion non convertie (500 au lieu de 503) ; test instable à cause d'une description aléatoire.
- **Fichiers créés ou modifiés :**
  - `docker-compose.yml`, `backend/config/search.php`, `backend/.env`, `backend/.env.example`, `backend/phpunit.xml`, `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`
  - `backend/app/Modules/Search/{ListingSearch.php,OpenSearch/OpenSearchClient.php,OpenSearch/SearchException.php,Index/ListingIndex.php,Jobs/SyncListingToIndex.php,Observers/SearchSyncObserver.php,Console/ReindexListings.php,Query/SearchCriteria.php,Query/ListingSearchQuery.php,Http/SearchRequest.php,Http/SearchController.php}`
  - `backend/database/seeders/{DemoListingsSeeder,DatabaseSeeder}.php`
  - Tests : `backend/tests/Unit/ListingSearchQueryTest.php`, `backend/tests/Feature/Search/SearchApiTest.php`
- **Reste à faire identifié :** page de résultats Angular (SSR) ; géocodage ville / code postal (RECH-04, base des communes en local) ; carte (RECH-05, « Should ») ; facettes « multi-sélection » qui ignorent leur propre filtre ; `search_after` au-delà de la page 100.
