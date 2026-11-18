import { Injectable, PLATFORM_ID, inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

const KEY = 'marketplace.token';

/**
 * Persistance du jeton API côté navigateur uniquement (aucun accès au stockage pendant le SSR).
 * Dette connue : passer au mode cookie HttpOnly de Sanctum (cahier des charges §6.2) avant la mise en production.
 */
@Injectable({ providedIn: 'root' })
export class TokenStorage {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  read(): string | null {
    if (!this.isBrowser) {
      return null;
    }
    try {
      return localStorage.getItem(KEY);
    } catch {
      return null;
    }
  }

  write(token: string): void {
    if (!this.isBrowser) {
      return;
    }
    try {
      localStorage.setItem(KEY, token);
    } catch {
      // Stockage indisponible (navigation privée stricte) : la session reste en mémoire.
    }
  }

  clear(): void {
    if (!this.isBrowser) {
      return;
    }
    try {
      localStorage.removeItem(KEY);
    } catch {
      // ignoré
    }
  }
}
