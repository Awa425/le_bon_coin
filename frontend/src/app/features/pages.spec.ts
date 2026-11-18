import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap, provideRouter } from '@angular/router';
import { API_BASE_URL } from '../core/api/api-config';
import { AuthService } from '../core/auth/auth.service';
import { text } from '../../testing/dom';
import { AccountPage } from './account/account.page';
import { ComingSoonPage } from './coming-soon/coming-soon.page';

const API = 'http://api.test/v1';

describe('ComingSoonPage', () => {
  it('affiche le titre de la route et rappelle la recherche saisie', async () => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap({ q: 'vélo' }) } } },
      ],
    });
    const fixture = TestBed.createComponent(ComingSoonPage);
    fixture.componentRef.setInput('heading', 'La recherche arrive bientôt');
    fixture.componentRef.setInput('icon', 'search');
    await fixture.whenStable();

    expect(text(fixture)).toContain('La recherche arrive bientôt');
    expect(text(fixture)).toContain('« vélo »');
    expect(text(fixture)).toContain("Retour à l'accueil");
  });
});

describe('AccountPage', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([]), { provide: API_BASE_URL, useValue: API }],
    });
  });

  it('affiche le profil et déconnecte l’utilisateur', async () => {
    const http = TestBed.inject(HttpTestingController);
    const auth = TestBed.inject(AuthService);
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    auth.login({ email: 'camille@example.fr', password: 'x' }).subscribe();
    http.expectOne(`${API}/auth/login`).flush({
      data: { id: 'u1', name: 'camille Martin', email: 'camille@example.fr', email_verified: true, phone: null, created_at: null },
      token: 't',
      token_type: 'Bearer',
    });

    const fixture = TestBed.createComponent(AccountPage);
    await fixture.whenStable();
    expect(text(fixture)).toContain('camille Martin');
    expect(text(fixture)).toContain('camille@example.fr');
    expect((fixture.nativeElement as HTMLElement).querySelector('.account__avatar')?.textContent).toBe('C');

    const logout = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll('button')).find((b) =>
      b.textContent?.includes('Se déconnecter'),
    )!;
    logout.click();
    http.expectOne(`${API}/auth/logout`).flush(null);

    expect(auth.isAuthenticated()).toBe(false);
    expect(navigate).toHaveBeenCalledWith('/');
    http.verify();
  });
});
