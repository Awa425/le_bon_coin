import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { API_BASE_URL } from '../api/api-config';
import { AuthResponse } from './auth.models';
import { authGuard, safeRedirect } from './auth.guard';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from './auth.service';

const API = 'http://api.test/v1';

const authResponse: AuthResponse = {
  data: { id: 'u1', name: 'Camille', email: 'camille@example.fr', email_verified: false, phone: null, created_at: null },
  token: '1|secret',
  token_type: 'Bearer',
};

describe('Authentification', () => {
  let auth: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: API_BASE_URL, useValue: API },
      ],
    });
    auth = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  describe('AuthService', () => {
    it('ouvre une session à la connexion et persiste le jeton', () => {
      let name: string | undefined;
      auth.login({ email: 'camille@example.fr', password: 'secret' }).subscribe((user) => (name = user.name));

      const request = http.expectOne(`${API}/auth/login`);
      expect(request.request.body).toEqual({ email: 'camille@example.fr', password: 'secret', device_name: 'web' });
      request.flush(authResponse);

      expect(name).toBe('Camille');
      expect(auth.isAuthenticated()).toBe(true);
      expect(auth.user()?.email).toBe('camille@example.fr');
      expect(localStorage.getItem('marketplace.token')).toBe('1|secret');
    });

    it('ferme la session locale même si la révocation serveur échoue', () => {
      auth.login({ email: 'a@b.fr', password: 'x' }).subscribe();
      http.expectOne(`${API}/auth/login`).flush(authResponse);

      auth.logout().subscribe();
      http.expectOne(`${API}/auth/logout`).flush(null, { status: 500, statusText: 'Erreur' });

      expect(auth.isAuthenticated()).toBe(false);
      expect(localStorage.getItem('marketplace.token')).toBeNull();
    });

    it('ne contacte pas le serveur au démarrage sans jeton', () => {
      let result: unknown = 'non appelé';
      auth.restore().subscribe((user) => (result = user));

      http.expectNone(`${API}/me`);
      expect(result).toBeNull();
    });
  });

  describe('authInterceptor', () => {
    it('ajoute le jeton aux seuls appels vers notre API', () => {
      auth.login({ email: 'a@b.fr', password: 'x' }).subscribe();
      http.expectOne(`${API}/auth/login`).flush(authResponse);
      const client = TestBed.inject(HttpClient);

      client.get(`${API}/me`).subscribe();
      client.get('https://tiers.example/donnees').subscribe();

      expect(http.expectOne(`${API}/me`).request.headers.get('Authorization')).toBe('Bearer 1|secret');
      expect(http.expectOne('https://tiers.example/donnees').request.headers.has('Authorization')).toBe(false);
    });

    it('ferme la session quand le serveur refuse le jeton (401)', () => {
      auth.login({ email: 'a@b.fr', password: 'x' }).subscribe();
      http.expectOne(`${API}/auth/login`).flush(authResponse);

      TestBed.inject(HttpClient).get(`${API}/me`).subscribe({ error: () => undefined });
      http.expectOne(`${API}/me`).flush(null, { status: 401, statusText: 'Unauthorized' });

      expect(auth.isAuthenticated()).toBe(false);
    });
  });

  describe('authGuard', () => {
    const runGuard = (url: string) =>
      TestBed.runInInjectionContext(() =>
        authGuard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot),
      );

    it('redirige un visiteur vers la connexion en mémorisant la cible', () => {
      const result = runGuard('/deposer');

      expect(result).toBeInstanceOf(UrlTree);
      expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/connexion?redirect=%2Fdeposer');
    });

    it('laisse passer un utilisateur connecté', () => {
      auth.login({ email: 'a@b.fr', password: 'x' }).subscribe();
      http.expectOne(`${API}/auth/login`).flush(authResponse);

      expect(runGuard('/deposer')).toBe(true);
    });
  });

  describe('safeRedirect', () => {
    it('refuse les redirections externes', () => {
      expect(safeRedirect('/deposer')).toBe('/deposer');
      expect(safeRedirect('//pirate.example')).toBe('/');
      expect(safeRedirect('/\\pirate.example')).toBe('/');
      expect(safeRedirect('https://pirate.example')).toBe('/');
      expect(safeRedirect(null)).toBe('/');
    });
  });
});
