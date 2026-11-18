import { InjectionToken } from '@angular/core';

/**
 * URL de base de l'API Laravel (/api/v1).
 * Surchargée par environnement via un provider (recette, production).
 */
export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', {
  providedIn: 'root',
  factory: () => 'http://localhost:8000/api/v1',
});
