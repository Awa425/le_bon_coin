import { RenderMode, ServerRoute } from '@angular/ssr';

/**
 * Rendu par type de page (cahier des charges §5.2) :
 * SSR pour les pages publiques, rendu client pour l'espace connecté et le dépôt.
 */
export const serverRoutes: ServerRoute[] = [
  { path: 'connexion', renderMode: RenderMode.Client },
  { path: 'inscription', renderMode: RenderMode.Client },
  { path: 'deposer', renderMode: RenderMode.Client },
  { path: 'compte', renderMode: RenderMode.Client },
  { path: 'favoris', renderMode: RenderMode.Client },
  { path: 'messages', renderMode: RenderMode.Client },
  { path: '**', renderMode: RenderMode.Server },
];
