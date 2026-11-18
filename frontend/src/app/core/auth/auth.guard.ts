import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/** Réserve une route aux utilisateurs connectés ; sinon redirige vers la connexion en mémorisant la cible. */
export const authGuard: CanActivateFn = (_route, state) => {
  if (inject(AuthService).isAuthenticated()) {
    return true;
  }

  return inject(Router).createUrlTree(['/connexion'], { queryParams: { redirect: state.url } });
};

/** N'accepte qu'un chemin interne (« /… », pas « //domaine ») pour éviter les redirections ouvertes. */
export function safeRedirect(target: string | null | undefined): string {
  return target && target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/\\') ? target : '/';
}
