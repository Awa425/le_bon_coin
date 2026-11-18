import { HttpErrorResponse } from '@angular/common/http';

/** Erreurs de validation Laravel (HTTP 422), indexées par champ (ex. « attribute_values.kilometrage »). */
export type ValidationErrors = Record<string, string[]>;

export interface ApiError {
  status: number;
  message: string;
  errors: ValidationErrors;
}

const FALLBACK_MESSAGES: Record<number, string> = {
  0: 'Serveur injoignable. Vérifiez votre connexion et réessayez.',
  401: 'Votre session a expiré. Veuillez vous reconnecter.',
  403: "Vous n'avez pas les droits pour effectuer cette action.",
  404: 'Ressource introuvable.',
  429: 'Trop de tentatives. Patientez une minute avant de réessayer.',
};

export function toApiError(error: unknown): ApiError {
  if (!(error instanceof HttpErrorResponse)) {
    return { status: -1, message: 'Une erreur inattendue est survenue.', errors: {} };
  }

  const body = (error.error ?? {}) as { message?: unknown; errors?: unknown };
  const errors = isValidationErrors(body.errors) ? body.errors : {};
  const message =
    error.status === 422 && typeof body.message === 'string'
      ? body.message
      : (FALLBACK_MESSAGES[error.status] ?? 'Une erreur est survenue. Réessayez plus tard.');

  return { status: error.status, message, errors };
}

/** Premier message d'erreur d'un champ, ou null. */
export function fieldError(errors: ValidationErrors, field: string): string | null {
  return errors[field]?.[0] ?? null;
}

function isValidationErrors(value: unknown): value is ValidationErrors {
  return (
    typeof value === 'object' &&
    value !== null &&
    Object.values(value).every((v) => Array.isArray(v) && v.every((m) => typeof m === 'string'))
  );
}
