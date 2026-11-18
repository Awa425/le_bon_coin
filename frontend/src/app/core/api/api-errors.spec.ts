import { HttpErrorResponse } from '@angular/common/http';
import { fieldError, toApiError } from './api-errors';

describe('toApiError', () => {
  it('extrait le message et les erreurs de validation Laravel (422)', () => {
    const error = toApiError(
      new HttpErrorResponse({
        status: 422,
        error: { message: 'Le champ prix est obligatoire.', errors: { price: ['Le champ prix est obligatoire.'] } },
      }),
    );

    expect(error.status).toBe(422);
    expect(error.message).toBe('Le champ prix est obligatoire.');
    expect(fieldError(error.errors, 'price')).toBe('Le champ prix est obligatoire.');
    expect(fieldError(error.errors, 'title')).toBeNull();
  });

  it('fournit un message français pour les statuts courants', () => {
    expect(toApiError(new HttpErrorResponse({ status: 0 })).message).toContain('Serveur injoignable');
    expect(toApiError(new HttpErrorResponse({ status: 429 })).message).toContain('Trop de tentatives');
    expect(toApiError(new HttpErrorResponse({ status: 500 })).message).toContain('Réessayez plus tard');
  });

  it('ignore un corps d’erreur mal formé', () => {
    const error = toApiError(new HttpErrorResponse({ status: 422, error: { errors: { price: 'pas un tableau' } } }));

    expect(error.errors).toEqual({});
  });

  it('gère une erreur qui ne vient pas de HttpClient', () => {
    expect(toApiError(new Error('boom')).status).toBe(-1);
  });
});
