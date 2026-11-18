# Marketplace C2C

Monorepo avec API Laravel dans `backend/` et application Angular SSR dans `frontend/`.

## Prerequis

- PHP 8.3+, Composer et les extensions PHP `pdo_pgsql` et `pgsql`
- Node.js compatible avec la version Angular du frontend
- Docker Compose pour la base de donnees PostgreSQL/PostGIS locale

## Demarrage local

1. Lancer PostgreSQL/PostGIS : `docker compose up -d postgres`
2. Dans `backend/`, copier `.env.example` vers `.env`, puis lancer `php artisan key:generate` et `php artisan migrate --seed` (catalogue de catégories et compte `test@example.com` / `password`).
3. Dans `backend/`, lancer l'API avec `php artisan serve` (http://localhost:8000, API sous `/api/v1`).
4. Dans `frontend/`, lancer `npm start` puis ouvrir http://localhost:4200 (l'URL de l'API est définie dans `src/app/core/api/api-config.ts`).

## API (v1)

| Méthode | Route | Accès |
| --- | --- | --- |
| POST | `/auth/register`, `/auth/login` | public (limité à 5/min) |
| POST | `/auth/logout` · GET `/me` | connecté |
| GET | `/categories`, `/categories/{slug}` | public |
| POST | `/listings` | connecté (5/heure) |
| GET | `/listings/{id}` | public si active, sinon auteur |
| PATCH | `/listings/{id}` · POST `/listings/{id}/submit` | auteur, brouillon ou refusée |

## Tests

- Backend : `docker compose up -d postgres`, puis `cd backend` et `php artisan test` (base PostGIS `marketplace_testing`, créée par `docker/postgres/init/`)
- Frontend : `cd frontend` puis `npm test -- --watch=false`

Les migrations (JSONB, PostGIS) ciblent PostgreSQL et ne sont pas compatibles avec SQLite : les tests tournent donc sur PostgreSQL.
Si le volume Docker existait avant l’ajout du script d’init, créer la base de test une fois : `docker compose exec -T postgres psql -U marketplace -d marketplace -f - < docker/postgres/init/01-create-testing-database.sql`.
