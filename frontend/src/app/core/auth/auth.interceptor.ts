import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { API_BASE_URL } from '../api/api-config';
import { AuthService } from './auth.service';

/** Ajoute le jeton aux seuls appels vers notre API, et ferme la session si le serveur le refuse (401). */
export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const api = inject(API_BASE_URL);

  if (!request.url.startsWith(api)) {
    return next(request);
  }

  const token = auth.token();
  const authorized = token ? request.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : request;

  return next(authorized).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && token) {
        auth.clearSession();
      }
      return throwError(() => error);
    }),
  );
};
