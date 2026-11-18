# Backlog marketplace pour GitHub Projects

Ce dossier contient le backlog issu du cahier des charges : 4 epics (un par lot), 55 user stories et 129 sous-tâches techniques, soit 188 lignes.

| Fichier | Rôle |
| --- | --- |
| `backlog.csv` | Le backlog structuré (UTF-8, séparateur virgule) |
| `generer_backlog.py` | Source du backlog : modifiez une story ici puis relancez `python3 generer_backlog.py` |
| `importer_github.py` | Crée les issues, sous-issues, labels et milestones, puis les ajoute à votre GitHub Project |

## Colonnes du CSV

| Colonne | Contenu |
| --- | --- |
| ID | `LOT1` à `LOT4` pour les epics, identifiant du CDC pour les stories (`RECH-01`), `RECH-01-T1` pour les sous-tâches |
| Type | Epic, Story ou Task |
| Title | Titre de l'issue ; les sous-tâches commencent par `[BACK-LARAVEL]`, `[FRONT-ANGULAR]` ou `[DEVOPS]` |
| Parent ID | ID du parent (epic pour une story, story pour une sous-tâche) |
| Epic | Lot 1 : MVP, Lot 2 : Transaction, Lot 3 : Pro, Lot 4 : IA |
| Component | BACK-LARAVEL, FRONT-ANGULAR ou DEVOPS (sous-tâches) |
| Priority | Must, Should, Could (MoSCoW du CDC) |
| Domain | Recherche, Fiche annonce, Achat sécurisé, etc. |
| Labels | Labels GitHub, séparés par des virgules |
| Body | Description Markdown : user story et critères d'acceptation du CDC en cases à cocher |

## Import en une commande

1. Créez le dépôt et un GitHub Project vide, puis notez le numéro du projet (dans l'URL).
2. Connectez la CLI GitHub avec l'accès aux projets : `gh auth login` puis `gh auth refresh -s project`.
3. Vérifiez à blanc : `python3 importer_github.py --repo OWNER/REPO --project 1 --dry-run`
4. Lancez l'import : `python3 importer_github.py --repo OWNER/REPO --project 1`

Le script est relançable : en cas de coupure, il reprend là où il s'est arrêté grâce au fichier `.backlog_state.json`.

## Vue sprint conseillée dans GitHub Projects

- Regroupez par **Milestone** pour voir les lots, ou par **Parent issue** pour voir les stories et leurs sous-tâches.
- Filtrez par label `back-laravel`, `front-angular` ou `devops` pour une vue par équipe.
- Ajoutez un champ **Iteration** (sprints de 2 semaines) et un champ **Estimate** pour planifier.
