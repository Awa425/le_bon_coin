import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, tap } from 'rxjs';
import { API_BASE_URL } from '../api/api-config';
import { AuthResponse, LoginPayload, RegisterPayload, User } from './auth.models';
import { TokenStorage } from './token-storage';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);
  private readonly storage = inject(TokenStorage);

  private readonly tokenState = signal<string | null>(this.storage.read());
  private readonly userState = signal<User | null>(null);

  readonly token = this.tokenState.asReadonly();
  readonly user = this.userState.asReadonly();
  readonly isAuthenticated = computed(() => this.tokenState() !== null);

  login(payload: LoginPayload): Observable<User> {
    return this.http
      .post<AuthResponse>(`${this.api}/auth/login`, { ...payload, device_name: 'web' })
      .pipe(map((response) => this.startSession(response)));
  }

  register(payload: RegisterPayload): Observable<User> {
    return this.http
      .post<AuthResponse>(`${this.api}/auth/register`, { ...payload, device_name: 'web' })
      .pipe(map((response) => this.startSession(response)));
  }

  /** Révoque le jeton côté serveur ; la session locale est fermée même si l'appel échoue. */
  logout(): Observable<void> {
    if (this.tokenState() === null) {
      return of(undefined);
    }

    return this.http.post<void>(`${this.api}/auth/logout`, {}).pipe(
      catchError(() => of(undefined)),
      tap(() => this.clearSession()),
      map(() => undefined),
    );
  }

  /** Recharge le profil au démarrage si un jeton est présent ; un jeton invalide est supprimé. */
  restore(): Observable<User | null> {
    if (this.tokenState() === null) {
      return of(null);
    }

    return this.http.get<{ data: User }>(`${this.api}/me`).pipe(
      map(({ data }) => {
        this.userState.set(data);
        return data;
      }),
      catchError(() => {
        this.clearSession();
        return of(null);
      }),
    );
  }

  clearSession(): void {
    this.storage.clear();
    this.tokenState.set(null);
    this.userState.set(null);
  }

  private startSession(response: AuthResponse): User {
    this.storage.write(response.token);
    this.tokenState.set(response.token);
    this.userState.set(response.data);
    return response.data;
  }
}
